<?php
require_once __DIR__ . '/config.php';
function ph($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
$sent = false;
$err = '';
if (empty($_SESSION['privacy_csrf'])) $_SESSION['privacy_csrf'] = bin2hex(random_bytes(24));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['privacy_contact'])) {
    try {
        if (!hash_equals((string)($_SESSION['privacy_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) throw new RuntimeException('Sesi formulir tidak valid.');
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '' || strlen($message) < 5) throw new RuntimeException('Pesan terlalu singkat.');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Alamat email tidak valid.');
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = $dir . '/privacy_requests.json';
        $fp = @fopen($file, 'c+');
        if (!$fp) throw new RuntimeException('Permintaan belum dapat disimpan. Coba lagi nanti.');
        flock($fp, LOCK_EX);
        rewind($fp);
        $data = json_decode((string)stream_get_contents($fp), true);
        if (!is_array($data)) $data = [];
        $data[] = ['name' => substr($name, 0, 100), 'email' => substr($email, 0, 180), 'message' => substr($message, 0, 2000), 'created_at' => date('Y-m-d H:i:s')];
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $sent = true;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Kebijakan Privasi - Catatan Keuangan</title>
    <link rel="icon" type="image/webp" href="assets/icon.webp">
    <style>
        :root {
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #182134;
            background: #f5f7fb
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0
        }

        .wrap {
            max-width: 900px;
            margin: 0 auto;
            padding: 34px 18px 70px
        }

        .brand {
            display: flex;
            gap: 12px;
            align-items: center
        }

        .brand img {
            width: 54px;
            height: 54px;
            border-radius: 15px
        }

        .card {
            background: #fff;
            border: 1px solid #e5eaf2;
            border-radius: 22px;
            padding: 26px;
            margin-top: 20px;
            box-shadow: 0 16px 45px rgba(32, 57, 93, .07)
        }

        h1 {
            margin: 0 0 8px;
            font-size: 30px
        }

        h2 {
            font-size: 19px;
            margin: 28px 0 8px
        }

        .muted,
        p,
        li {
            color: #606d82;
            line-height: 1.7
        }

        a {
            color: #175cd3
        }

        .note {
            background: #edf4ff;
            border: 1px solid #d7e5ff;
            padding: 14px 16px;
            border-radius: 14px
        }

        .contact {
            display: grid;
            gap: 9px
        }

        input,
        textarea {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #d8e0eb;
            border-radius: 11px;
            font: inherit
        }

        .btn {
            background: #175cd3;
            color: white;
            border: 0;
            border-radius: 11px;
            padding: 12px 16px;
            font-weight: 800;
            cursor: pointer
        }

        .ok {
            background: #ecf9f1;
            color: #126d3a;
            padding: 11px;
            border-radius: 10px
        }

        .err {
            background: #fff0f0;
            color: #9e2424;
            padding: 11px;
            border-radius: 10px
        }

        @media(max-width:540px) {
            .wrap {
                padding-top: 18px
            }

            .card {
                padding: 18px;
                border-radius: 18px
            }

            h1 {
                font-size: 25px
            }
        }
    </style>
</head>

<body>
    <main class="wrap">
        <div class="brand"><img src="assets/icon.webp" alt="Catatan Keuangan">
            <div><b>Catatan Keuangan</b>
                <div class="muted">Kebijakan Privasi</div>
            </div>
        </div>
        <article class="card">
            <h1>Kebijakan Privasi Catatan Keuangan</h1>
            <p class="muted">Terakhir diperbarui: 11 September 2026</p>
            <div class="note">Catatan Keuangan adalah aplikasi pencatatan keuangan pribadi. Aplikasi tidak memberikan
                pinjaman, layanan perbankan, transfer uang nyata, atau jasa investasi. Fitur dompet/rekening di dalam
                aplikasi digunakan untuk pencatatan pribadi.</div>
            <h2>Data yang kami proses</h2>
            <ul>
                <li>Data akun seperti username, alamat email, status verifikasi email, hash password/PIN, serta token sesi/perangkat aplikasi.</li>
                <li>Data keuangan yang Anda masukkan sendiri, termasuk saldo, pemasukan, pengeluaran, kategori, budget,
                    tagihan, target, dan catatan.</li>
                <li>Foto nota/lampiran yang secara opsional Anda unggah.</li>
                <li>Isi chat/asisten dan aturan pembelajaran yang Anda berikan.</li>
                <li>Data pembelian Premium, termasuk paket yang dipilih, nomor invoice, bank tujuan, nama pengirim, nomor rekening pengirim, status pembayaran, dan gambar bukti pembayaran yang Anda unggah.</li>
                <li>Permintaan dukungan atau privasi yang Anda kirim melalui formulir pada halaman ini.</li>
            </ul>
            <h2>Tujuan penggunaan</h2>
            <p>Data digunakan untuk menyediakan fungsi pencatatan, sinkronisasi online/offline, autentikasi, laporan,
                OCR nota, analitik pribadi, backup, dukungan pengguna, serta membuat invoice, memverifikasi pembayaran
                Premium secara manual, mengaktifkan hak akses Premium sesuai pembelian, serta mengirim kode verifikasi dan token pemulihan password/PIN ke email yang terdaftar.</p>
            <h2>Pembayaran Premium</h2>
            <p>Pembayaran Premium dilakukan melalui transfer manual ke rekening yang ditampilkan pada invoice.
                Catatan Keuangan bukan penyedia jasa pembayaran dan tidak meminta PIN, password internet banking, CVV,
                atau OTP bank. Nama pengirim, nomor rekening pengirim, detail invoice, dan bukti transfer hanya digunakan
                oleh admin untuk mencocokkan serta memverifikasi pembayaran. Bukti pembayaran dapat dilihat oleh pemilik
                akun terkait dan admin yang berwenang.</p>
            <h2>Penyimpanan dan keamanan</h2>
            <p>Komunikasi antara aplikasi dan domain Catatan Keuangan menggunakan HTTPS. Password dan PIN disimpan dalam
                bentuk hash. Sebagian data juga dapat disimpan pada perangkat melalui cache/IndexedDB agar fitur offline
                dapat digunakan. Data server dipisahkan per akun.</p>
            <h2>OCR dan layanan pihak ketiga</h2>
            <p>Pengenalan teks nota menggunakan Tesseract.js di perangkat/browser. File library OCR dapat dimuat dari
                CDN jsDelivr. Isi foto tidak dikirim ke jsDelivr oleh fungsi OCR Catatan Keuangan, tetapi foto dapat
                diunggah ke server Catatan Keuangan bila Anda memilih menyimpannya sebagai lampiran/transaksi.</p>
            <h2>Berbagi data</h2>
            <p>Catatan Keuangan tidak menjual data pribadi Anda. Data dapat diproses oleh penyedia infrastruktur/hosting dan penyedia layanan email/SMTP
                yang diperlukan untuk menjalankan layanan, termasuk pengiriman kode verifikasi dan token pemulihan akun. Kami tidak menggunakan SDK iklan atau analytics pihak ketiga
                pada aplikasi Android ini.</p>
            <h2>Retensi dan penghapusan</h2>
            <p>Data akun disimpan selama akun aktif atau sampai pengguna meminta penghapusan. Pengguna dapat menghapus
                akun dan data terkait melalui <a href="delete-account.php">halaman Hapus Akun</a>. Penghapusan akun juga
                menghapus data pembelian Premium dan bukti pembayaran yang terkait dengan akun tersebut dari penyimpanan
                aplikasi. Setelah penghapusan, salinan offline pada perangkat pengguna dapat tetap ada sampai pengguna
                membersihkan data aplikasi/browser.</p>
            <h2>Hak dan kontrol pengguna</h2>
            <p>Anda dapat mengedit atau menghapus transaksi, mengekspor data, membuat backup, mengatur visibilitas
                saldo, dan meminta penghapusan akun.</p>
            <h2 id="contact">Kontak privasi</h2>
            <p>Gunakan formulir ini untuk pertanyaan tentang privasi atau data Anda.</p>
            <?php if ($sent): ?><div class="ok">Permintaan Anda telah tersimpan. Silakan simpan waktu pengiriman sebagai
                    referensi.</div><?php endif; ?><?php if ($err): ?><div class="err"><?= ph($err) ?></div><?php endif; ?>
            <form method="post" class="contact"><input type="hidden" name="privacy_contact" value="1"><input
                    type="hidden" name="csrf" value="<?= ph($_SESSION['privacy_csrf']) ?>"><input name="name"
                    maxlength="100" placeholder="Nama (opsional)"><input name="email" type="email" maxlength="180"
                    placeholder="Email balasan (opsional)"><textarea name="message" rows="5" maxlength="2000" required
                    placeholder="Tuliskan pertanyaan atau permintaan privasi Anda"></textarea><button class="btn"
                    type="submit">Kirim permintaan</button></form>
            <p style="margin-top:24px"><a href="index.php">Kembali ke Catatan Keuangan</a> · <a
                    href="delete-account.php">Hapus akun</a></p>
        </article>
    </main>
</body>

</html>