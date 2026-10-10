<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/mail_config.php';

function financeMailHeaderEncode($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    return '=?UTF-8?B?'.base64_encode($value).'?=';
}

function financeMailFromAddress() {
    $from = trim((string)MAIL_FROM_EMAIL);
    if ($from === '' && trim((string)MAIL_SMTP_USERNAME) !== '') $from = trim((string)MAIL_SMTP_USERNAME);
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Alamat pengirim email belum dikonfigurasi dengan benar di mail_config.php.');
    }
    return $from;
}

function financeSmtpReadResponse($socket) {
    $response = '';
    $code = 0;
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) break;
        $response .= $line;
        if (preg_match('/^(\d{3})([ -])/', $line, $m)) {
            $code = (int)$m[1];
            if ($m[2] === ' ') break;
        }
    }
    return [$code, trim($response)];
}

function financeSmtpExpect($socket, $allowedCodes, $context='SMTP') {
    [$code, $response] = financeSmtpReadResponse($socket);
    $allowedCodes = array_map('intval', (array)$allowedCodes);
    if (!in_array($code, $allowedCodes, true)) {
        throw new RuntimeException($context.' gagal. Respons server: '.($response !== '' ? $response : 'tidak ada respons'));
    }
    return $response;
}

function financeSmtpCommand($socket, $command, $allowedCodes, $context='SMTP') {
    if ($command !== '') {
        $written = fwrite($socket, $command."\r\n");
        if ($written === false) throw new RuntimeException($context.' gagal mengirim perintah ke server SMTP.');
    }
    return financeSmtpExpect($socket, $allowedCodes, $context);
}

function financeSmtpWriteAll($socket, string $data, string $context='SMTP'): void {
    $length = strlen($data);
    $offset = 0;
    while ($offset < $length) {
        $written = fwrite($socket, substr($data, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException($context.' gagal menulis data ke server SMTP.');
        }
        $offset += $written;
    }
}

function financeMailBase64Part(string $value): string {
    return rtrim(chunk_split(base64_encode($value), 76, "\r\n"));
}

/**
 * Mendeteksi pembatasan kuota harian provider email.
 * Gmail mengembalikan 550-5.4.5 / "Daily user sending limit exceeded".
 * Fungsi ini sengaja generik agar fitur broadcast dapat menahan antrean
 * tanpa menandai penerima sebagai gagal permanen.
 */
function financeMailIsDailySendingLimitError($error): bool {
    $message = $error instanceof Throwable ? $error->getMessage() : (string)$error;
    $message = strtolower(trim($message));
    if ($message === '') return false;
    $needles = [
        'daily user sending limit exceeded',
        '550-5.4.5',
        '550 5.4.5',
        'daily sending limit',
        'sending limit exceeded',
    ];
    foreach ($needles as $needle) {
        if (strpos($message, $needle) !== false) return true;
    }
    return false;
}

function financeSmtpSend($to, $subject, $html, $text='') {
    $host = trim((string)MAIL_SMTP_HOST);
    $port = (int)MAIL_SMTP_PORT;
    $username = trim((string)MAIL_SMTP_USERNAME);
    // Google App Password sering ditulis berkelompok dengan spasi. SMTP AUTH memerlukan nilai tanpa spasi.
    $password = preg_replace('/\s+/', '', (string)MAIL_SMTP_PASSWORD);
    $encryption = strtolower(trim((string)MAIL_SMTP_ENCRYPTION));
    $timeout = max(5, (int)MAIL_SMTP_TIMEOUT);

    if ($host === '') throw new RuntimeException('SMTP belum dikonfigurasi. Isi MAIL_SMTP_HOST pada mail_config.php.');
    if ($port < 1 || $port > 65535) throw new RuntimeException('Port SMTP tidak valid.');
    if (!in_array($encryption, ['tls','ssl','none',''], true)) throw new RuntimeException('MAIL_SMTP_ENCRYPTION harus tls, ssl, atau none.');

    $remote = ($encryption === 'ssl' ? 'ssl://' : '').$host.':'.$port;
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) throw new RuntimeException('Tidak dapat terhubung ke SMTP: '.($errstr ?: ('error '.$errno)).'.');
    stream_set_timeout($socket, $timeout);

    try {
        financeSmtpExpect($socket, [220], 'Koneksi SMTP');
        $helo = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
        financeSmtpCommand($socket, 'EHLO '.$helo, [250], 'EHLO SMTP');

        if ($encryption === 'tls') {
            financeSmtpCommand($socket, 'STARTTLS', [220], 'STARTTLS');
            $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoOk !== true) throw new RuntimeException('Gagal mengaktifkan enkripsi TLS SMTP.');
            financeSmtpCommand($socket, 'EHLO '.$helo, [250], 'EHLO setelah TLS');
        }

        if ($username !== '') {
            if ($password === '') throw new RuntimeException('Password SMTP belum diisi.');
            financeSmtpCommand($socket, 'AUTH LOGIN', [334], 'Autentikasi SMTP');
            financeSmtpCommand($socket, base64_encode($username), [334], 'Username SMTP');
            financeSmtpCommand($socket, base64_encode($password), [235], 'Password SMTP');
        }

        $from = financeMailFromAddress();
        financeSmtpCommand($socket, 'MAIL FROM:<'.$from.'>', [250], 'MAIL FROM');
        financeSmtpCommand($socket, 'RCPT TO:<'.$to.'>', [250,251], 'RCPT TO');
        financeSmtpCommand($socket, 'DATA', [354], 'DATA SMTP');

        $boundary = '=_Finance_'.bin2hex(random_bytes(12));
        $fromName = financeMailHeaderEncode((string)MAIL_FROM_NAME);
        $messageId = '<'.bin2hex(random_bytes(12)).'@'.($helo ?: 'localhost').'>';
        $headers = [
            'Date: '.date(DATE_RFC2822),
            'From: '.$fromName.' <'.$from.'>',
            'Reply-To: '.$from,
            'To: <'.$to.'>',
            'Subject: '.financeMailHeaderEncode($subject),
            'Message-ID: '.$messageId,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
        ];

        $text = trim((string)$text);
        if ($text === '') $text = trim(html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'], "\n", $html)), ENT_QUOTES|ENT_HTML5, 'UTF-8'));

        // Base64 untuk body membuat email UTF-8 lebih stabil di shared hosting/relay SMTP
        // dan menghindari perubahan karakter/line wrapping oleh server di tengah jalan.
        $body = implode("\r\n", $headers)."\r\n\r\n";
        $body .= '--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".financeMailBase64Part($text)."\r\n\r\n";
        $body .= '--'.$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".financeMailBase64Part($html)."\r\n\r\n";
        $body .= '--'.$boundary."--\r\n";

        financeSmtpWriteAll($socket, $body."\r\n.\r\n", 'Isi email SMTP');
        $smtpResponse = financeSmtpExpect($socket, [250], 'Pengiriman email');
        @fwrite($socket, "QUIT\r\n");
    } finally {
        @fclose($socket);
    }
    return [
        'accepted'=>true,
        'transport'=>'smtp',
        'message_id'=>$messageId ?? '',
        'smtp_response'=>$smtpResponse ?? '',
    ];
}

function financeSendMail($to, $subject, $html, $text='') {
    $to = strtolower(trim((string)$to));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Alamat email tujuan tidak valid.');
    $transport = strtolower(trim((string)MAIL_TRANSPORT));

    if ($transport === 'smtp') return financeSmtpSend($to, $subject, $html, $text);
    if ($transport !== 'mail') throw new RuntimeException('MAIL_TRANSPORT harus diisi mail atau smtp.');

    $from = financeMailFromAddress();
    $fromName = financeMailHeaderEncode((string)MAIL_FROM_NAME);
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: '.$fromName.' <'.$from.'>',
        'Reply-To: '.$from,
        'X-Mailer: PHP/'.PHP_VERSION,
    ];
    $ok = @mail($to, financeMailHeaderEncode($subject), $html, implode("\r\n", $headers));
    if (!$ok) throw new RuntimeException('Email tidak dapat dikirim oleh server. Jika hosting menonaktifkan mail(), gunakan SMTP pada mail_config.php.');
    return ['accepted'=>true,'transport'=>'mail','message_id'=>'','smtp_response'=>''];
}

function financeSecurityEmailHtml($title, $intro, $code, $expiresMinutes=15) {
    $titleEsc = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
    $introEsc = htmlspecialchars((string)$intro, ENT_QUOTES, 'UTF-8');
    $codeEsc = htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8');
    $appEsc = htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8');
    return '<!doctype html><html><body style="margin:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#101828">'
        .'<div style="max-width:520px;margin:32px auto;padding:0 16px"><div style="background:#fff;border:1px solid #e4e7ec;border-radius:20px;padding:28px;box-shadow:0 8px 30px rgba(16,24,40,.08)">'
        .'<div style="font-size:12px;font-weight:700;color:#175cd3;margin-bottom:8px">'.$appEsc.'</div>'
        .'<h2 style="margin:0 0 10px;font-size:22px">'.$titleEsc.'</h2>'
        .'<p style="margin:0 0 20px;color:#667085;font-size:14px;line-height:1.6">'.$introEsc.'</p>'
        .'<div style="letter-spacing:8px;text-align:center;font-size:30px;font-weight:800;background:#edf4ff;color:#174ea6;border-radius:16px;padding:18px 12px">'.$codeEsc.'</div>'
        .'<p style="margin:18px 0 0;color:#667085;font-size:12px;line-height:1.6">Kode berlaku selama '.(int)$expiresMinutes.' menit dan hanya dapat digunakan satu kali. Jangan berikan kode ini kepada siapa pun.</p>'
        .'<p style="margin:10px 0 0;color:#98a2b3;font-size:11px">Jika Anda tidak meminta kode ini, abaikan email ini.</p>'
        .'</div></div></body></html>';
}
