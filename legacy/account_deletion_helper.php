<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/subscription_helper.php';
require_once __DIR__.'/learning_helper.php';

function cfRecursiveDelete($path) {
    if (!file_exists($path)) return;
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    $items = scandir($path);
    if (!is_array($items)) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        cfRecursiveDelete($path.DIRECTORY_SEPARATOR.$item);
    }
    @rmdir($path);
}

function cfDeleteAccountVerifyAndDelete($username, $secret, $secretType='password') {
    $username = strtolower(trim((string)$username));
    $secret = (string)$secret;
    $secretType = $secretType === 'pin' ? 'pin' : 'password';
    if ($username === '' || $secret === '') throw new RuntimeException('Username dan data verifikasi wajib diisi.');

    // Simpan referensi bukti pembayaran sebelum user dihapus. FK MySQL akan menghapus order
    // melalui CASCADE sehingga nama file proof tidak lagi bisa dibaca setelah delete user.
    $preProofFiles=[];
    $preUser=authFindUserByUsername($username);
    if($preUser){$preUid=(int)($preUser['id']??0);foreach((subscriptionReadData()['orders']??[]) as $order){if((int)($order['user_id']??0)===$preUid){$f=basename((string)($order['proof']['file']??''));if($f!=='')$preProofFiles[$f]=true;}}}

    $result = authMutateData(function (&$data) use ($username, $secret, $secretType) {
        $now = time();
        foreach ($data['users'] as $idx => &$user) {
            if (strtolower((string)($user['username'] ?? '')) !== $username) continue;

            $lockedUntil = !empty($user['delete_locked_until']) ? strtotime((string)$user['delete_locked_until']) : 0;
            if ($lockedUntil && $lockedUntil > $now) {
                $minutes = max(1, (int)ceil(($lockedUntil - $now) / 60));
                return ['ok'=>false,'error'=>'Terlalu banyak percobaan. Coba lagi sekitar '.$minutes.' menit.'];
            }

            $verified = false;
            if ($secretType === 'pin') {
                $verified = !empty($user['pin_hash']) && password_verify($secret, (string)$user['pin_hash']);
            } else {
                $verified = !empty($user['password_hash']) && password_verify($secret, (string)$user['password_hash']);
            }

            if (!$verified) {
                $attempts = (int)($user['delete_attempts'] ?? 0) + 1;
                if ($attempts >= 5) {
                    $user['delete_attempts'] = 0;
                    $user['delete_locked_until'] = date('Y-m-d H:i:s', $now + 900);
                    return ['ok'=>false,'error'=>'Data verifikasi tidak cocok. Penghapusan akun dikunci selama 15 menit.'];
                }
                $user['delete_attempts'] = $attempts;
                $user['delete_locked_until'] = '';
                return ['ok'=>false,'error'=>'Data verifikasi tidak cocok.'];
            }

            $deleted = $user;
            array_splice($data['users'], $idx, 1);
            return ['ok'=>true,'user'=>$deleted];
        }
        unset($user);
        return ['ok'=>false,'error'=>'Data verifikasi tidak cocok.'];
    });

    if (empty($result['ok'])) throw new RuntimeException((string)($result['error'] ?? 'Penghapusan akun gagal.'));
    $user = $result['user'];
    $uid = (int)$user['id'];

    // Hapus data keuangan dan lampiran user dari server.
    // Data keuangan MySQL terhapus otomatis melalui FK CASCADE saat user dihapus.
    cfRecursiveDelete(Yii::$app->params['privateStorage'].'/receipts/user_'.$uid);

    // Hapus file bukti yang referensinya sudah ditangkap sebelum FK CASCADE.
    foreach(array_keys($preProofFiles) as $proofFile) @unlink(PAYMENT_PROOF_DIR.'/'.$proofFile);

    // Bersihkan invoice langganan secara kompatibel (pada MySQL biasanya sudah terhapus oleh FK CASCADE).
    subscriptionMutateData(function (&$subscriptionData) use ($uid) {
        $kept = [];
        foreach (($subscriptionData['orders'] ?? []) as $order) {
            if ((int)($order['user_id'] ?? 0) === $uid) {
                $proofFile = basename((string)($order['proof']['file'] ?? ''));
                if ($proofFile !== '') @unlink(PAYMENT_PROOF_DIR.'/'.$proofFile);
                continue;
            }
            $kept[] = $order;
        }
        $subscriptionData['orders'] = $kept;
        return true;
    });

    // Anonimkan referensi user pada pembelajaran bersama, tanpa menghapus aturan global.
    learningMutateData(function (&$learning) use ($uid) {
        foreach (($learning['rules'] ?? []) as &$rule) {
            if ((int)($rule['created_by_user_id'] ?? 0) === $uid) $rule['created_by_user_id'] = 0;
            if ((int)($rule['updated_by_user_id'] ?? 0) === $uid) $rule['updated_by_user_id'] = 0;
        }
        unset($rule);
        return true;
    });

    // Bersihkan sesi jika akun yang dihapus adalah akun yang sedang login.
    if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $uid) {
        setcookie(DEVICE_COOKIE, '', time()-3600, '/', '', false, true);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        @session_destroy();
    }

    return $user;
}
