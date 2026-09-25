<?php
require_once __DIR__ . '/config.php';
use app\repositories\AuthRepository;
require_once __DIR__ . '/mail_helper.php';

define('DEVICE_COOKIE', 'finance_device');
define('DEVICE_COOKIE_DAYS', 180);

function authDefaultData()
{
    return ['users' => [], 'meta' => ['next_user_id' => 1]];
}

function authEnsureData(){ return true; }

function authReadData(){ return AuthRepository::readAll(); }

function authMutateData($fn){ return AuthRepository::mutateAll($fn); }
function authMutateUserData($userId,$fn){ return AuthRepository::mutateUserCompat((int)$userId,$fn); }

function authFindUserByUsername($username){ return AuthRepository::findByUsername(trim((string)$username)); }


function authNormalizeEmail($email)
{
    return strtolower(trim((string)$email));
}

function authFindUserByEmail($email){ $email=authNormalizeEmail($email); return $email===''?null:AuthRepository::findByEmail($email); }

function authFindUserByIdentifier($identifier)
{
    $identifier = trim((string)$identifier);
    if ($identifier === '') return null;
    if (strpos($identifier, '@') !== false) return authFindUserByEmail($identifier);
    return authFindUserByUsername($identifier);
}

function authMaskEmail($email)
{
    $email = authNormalizeEmail($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return '';
    [$local, $domain] = explode('@', $email, 2);
    $len = strlen($local);
    if ($len <= 2) $maskedLocal = substr($local, 0, 1) . '*';
    else $maskedLocal = substr($local, 0, 2) . str_repeat('*', min(6, max(2, $len - 2)));
    return $maskedLocal . '@' . $domain;
}

function authEmailStatus($user = null)
{
    if (!$user) $user = authCurrentUser();
    $email = authNormalizeEmail($user['email'] ?? '');
    $verifiedAt = trim((string)($user['email_verified_at'] ?? ''));
    return [
        'email' => $email,
        'has_email' => $email !== '',
        'verified' => $email !== '' && $verifiedAt !== '',
        'verified_at' => $verifiedAt,
        'masked' => $email !== '' ? authMaskEmail($email) : '',
    ];
}

function authFindUserById($id){ return AuthRepository::findById((int)$id); }

function authClientIp()
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    foreach ($candidates as $ip) {
        $ip = trim((string)$ip);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '';
}

function authDescribeDevice($userAgent = null)
{
    $ua = trim((string)($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    $low = strtolower($ua);

    $os = 'Perangkat tidak dikenal';
    if (strpos($low, 'iphone') !== false) $os = 'iPhone';
    elseif (strpos($low, 'ipad') !== false) $os = 'iPad';
    elseif (strpos($low, 'android') !== false) $os = 'Android';
    elseif (strpos($low, 'windows') !== false) $os = 'Windows';
    elseif (strpos($low, 'macintosh') !== false || strpos($low, 'mac os x') !== false) $os = 'Mac';
    elseif (strpos($low, 'linux') !== false) $os = 'Linux';

    $browser = 'Browser';
    if (strpos($low, 'charliefinanceandroid') !== false || strpos($low, '; wv)') !== false || strpos($low, ' version/4.0 ') !== false && strpos($low, 'android') !== false) {
        $browser = 'Aplikasi Catatan Keuangan (Android)';
    } elseif (strpos($low, 'edg/') !== false) $browser = 'Microsoft Edge';
    elseif (strpos($low, 'opr/') !== false || strpos($low, 'opera') !== false) $browser = 'Opera';
    elseif (strpos($low, 'firefox/') !== false || strpos($low, 'fxios/') !== false) $browser = 'Firefox';
    elseif (strpos($low, 'crios/') !== false) $browser = 'Chrome iOS';
    elseif (strpos($low, 'chrome/') !== false) $browser = 'Google Chrome';
    elseif (strpos($low, 'safari/') !== false) $browser = 'Safari';

    $label = $os;
    if ($browser !== 'Browser') $label .= ' · ' . $browser;
    return ['label' => $label, 'os' => $os, 'browser' => $browser, 'user_agent' => $ua];
}

function authDevicePublicId($deviceToken)
{
    $id = trim((string)($deviceToken['id'] ?? ''));
    if ($id !== '') return $id;
    $hash = (string)($deviceToken['hash'] ?? '');
    return $hash !== '' ? 'legacy_' . substr($hash, 0, 20) : '';
}

function authCurrentDeviceHash()
{
    $token = (string)($_COOKIE[DEVICE_COOKIE] ?? '');
    return $token !== '' ? hash('sha256', $token) : '';
}

function authIssueDeviceToken($userId)
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $deviceId = bin2hex(random_bytes(12));
    $info = authDescribeDevice();
    $now = date('Y-m-d H:i:s');
    $ip = authClientIp();

    authMutateUserData($userId, function (&$data) use ($userId, $hash, $deviceId, $info, $now, $ip) {
        foreach ($data['users'] as &$user) {
            if ((int)$user['id'] === (int)$userId) {
                if (!isset($user['device_tokens']) || !is_array($user['device_tokens'])) $user['device_tokens'] = [];
                $user['device_tokens'][] = [
                    'id' => $deviceId,
                    'hash' => $hash,
                    'created_at' => $now,
                    'last_used_at' => $now,
                    'device_name' => $info['label'],
                    'os' => $info['os'],
                    'browser' => $info['browser'],
                    'user_agent' => $info['user_agent'],
                    'last_ip' => $ip,
                ];
                if (count($user['device_tokens']) > 12) $user['device_tokens'] = array_slice($user['device_tokens'], -12);
                break;
            }
        }
        unset($user);
    });

    $secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    setcookie(DEVICE_COOKIE, $token, [
        'expires' => time() + (86400 * DEVICE_COOKIE_DAYS),
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // setcookie() tidak mengubah $_COOKIE pada request yang sama.
    $_COOKIE[DEVICE_COOKIE] = $token;
    $_SESSION['auth_device_id'] = $deviceId;
    $_SESSION['auth_device_checked_at'] = time();
    return $deviceId;
}

function authResolveDevice(){ $hash=authCurrentDeviceHash(); if($hash==='')return null; $u=AuthRepository::findByDeviceHash($hash); if($u){ foreach(($u['device_tokens']??[]) as $dt){ if(!empty($dt['hash'])&&hash_equals((string)$dt['hash'],$hash)){ $_SESSION['auth_device_id']=authDevicePublicId($dt); break; } } } return $u; }

function authClearLocalSession()
{
    setcookie(DEVICE_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    unset($_COOKIE[DEVICE_COOKIE]);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

function authSessionDeviceIsValid($userId)
{
    $hash = authCurrentDeviceHash();
    if ($hash === '') return false;
    $user = authFindUserById($userId);
    if (!$user) return false;
    foreach (($user['device_tokens'] ?? []) as $dt) {
        if (!empty($dt['hash']) && hash_equals((string)$dt['hash'], $hash)) {
            $_SESSION['auth_device_id'] = authDevicePublicId($dt);
            return true;
        }
    }
    return false;
}

function authTouchCurrentDevice($userId, $force = false)
{
    $hash = authCurrentDeviceHash();
    if ($hash === '') return;
    $lastTouch = (int)($_SESSION['auth_device_touched_at'] ?? 0);
    if (!$force && $lastTouch > 0 && (time() - $lastTouch) < 300) return;

    $now = date('Y-m-d H:i:s');
    $ip = authClientIp();
    $info = authDescribeDevice();
    authMutateUserData($userId, function (&$data) use ($userId, $hash, $now, $ip, $info) {
        foreach ($data['users'] as &$user) {
            if ((int)($user['id'] ?? 0) !== (int)$userId) continue;
            foreach (($user['device_tokens'] ?? []) as &$dt) {
                if (!empty($dt['hash']) && hash_equals((string)$dt['hash'], $hash)) {
                    if (empty($dt['id'])) $dt['id'] = bin2hex(random_bytes(12));
                    $dt['last_used_at'] = $now;
                    $dt['device_name'] = $info['label'];
                    $dt['os'] = $info['os'];
                    $dt['browser'] = $info['browser'];
                    $dt['user_agent'] = $info['user_agent'];
                    if ($ip !== '') $dt['last_ip'] = $ip;
                    $_SESSION['auth_device_id'] = authDevicePublicId($dt);
                    break 2;
                }
            }
            unset($dt);
        }
        unset($user);
    });
    $_SESSION['auth_device_touched_at'] = time();
}

function authListDevices($userId)
{
    $user = authFindUserById((int)$userId);
    if (!$user) return [];
    $currentHash = authCurrentDeviceHash();
    $devices = [];
    foreach (($user['device_tokens'] ?? []) as $dt) {
        $hash = (string)($dt['hash'] ?? '');
        if ($hash === '') continue;
        $info = authDescribeDevice($dt['user_agent'] ?? '');
        $devices[] = [
            'id' => authDevicePublicId($dt),
            'name' => (string)($dt['device_name'] ?? $info['label']),
            'os' => (string)($dt['os'] ?? $info['os']),
            'browser' => (string)($dt['browser'] ?? $info['browser']),
            'created_at' => (string)($dt['created_at'] ?? ''),
            'last_used_at' => (string)($dt['last_used_at'] ?? ($dt['created_at'] ?? '')),
            'last_ip' => (string)($dt['last_ip'] ?? ''),
            'current' => $currentHash !== '' && hash_equals($hash, $currentHash),
            'last_used_ts' => !empty($dt['last_used_at']) ? (int)strtotime((string)$dt['last_used_at']) : 0,
        ];
    }
    usort($devices, function ($a, $b) {
        if ($a['current'] !== $b['current']) return $a['current'] ? -1 : 1;
        return strcmp((string)$b['last_used_at'], (string)$a['last_used_at']);
    });
    return $devices;
}

function authRevokeDevice($userId, $deviceId)
{
    $userId = (int)$userId;
    $deviceId = trim((string)$deviceId);
    if ($userId <= 0 || $deviceId === '') throw new RuntimeException('Perangkat tidak valid.');
    $currentHash = authCurrentDeviceHash();
    $result = authMutateUserData($userId, function (&$data) use ($userId, $deviceId, $currentHash) {
        foreach ($data['users'] as &$user) {
            if ((int)($user['id'] ?? 0) !== $userId) continue;
            $found = false;
            $wasCurrent = false;
            $kept = [];
            foreach (($user['device_tokens'] ?? []) as $dt) {
                $publicId = authDevicePublicId($dt);
                if ($publicId !== '' && hash_equals($publicId, $deviceId)) {
                    $found = true;
                    $hash = (string)($dt['hash'] ?? '');
                    $wasCurrent = $currentHash !== '' && $hash !== '' && hash_equals($hash, $currentHash);
                    continue;
                }
                $kept[] = $dt;
            }
            if (!$found) return ['ok' => false, 'error' => 'Perangkat tidak ditemukan atau sudah dikeluarkan.'];
            $user['device_tokens'] = array_values($kept);
            return ['ok' => true, 'current' => $wasCurrent];
        }
        unset($user);
        return ['ok' => false, 'error' => 'Akun tidak ditemukan.'];
    });
    if (empty($result['ok'])) throw new RuntimeException((string)($result['error'] ?? 'Gagal mengeluarkan perangkat.'));
    return $result;
}

function authRevokeOtherDevices($userId)
{
    $userId = (int)$userId;
    $currentHash = authCurrentDeviceHash();
    if ($currentHash === '') throw new RuntimeException('Perangkat saat ini tidak dapat dikenali. Silakan login ulang.');
    $result = authMutateUserData($userId, function (&$data) use ($userId, $currentHash) {
        foreach ($data['users'] as &$user) {
            if ((int)($user['id'] ?? 0) !== $userId) continue;
            $current = [];
            foreach (($user['device_tokens'] ?? []) as $dt) {
                $hash = (string)($dt['hash'] ?? '');
                if ($hash !== '' && hash_equals($hash, $currentHash)) $current[] = $dt;
            }
            if (!$current) return ['ok' => false, 'error' => 'Perangkat saat ini tidak ditemukan. Silakan login ulang.'];
            $removed = max(0, count($user['device_tokens'] ?? []) - count($current));
            $user['device_tokens'] = array_values($current);
            return ['ok' => true, 'removed' => $removed];
        }
        unset($user);
        return ['ok' => false, 'error' => 'Akun tidak ditemukan.'];
    });
    if (empty($result['ok'])) throw new RuntimeException((string)($result['error'] ?? 'Gagal mengeluarkan perangkat lain.'));
    return $result;
}

function authBootstrap()
{
    if (!empty($_SESSION['user_id'])) return;
    $user = authResolveDevice();
    if ($user) {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['pin_verified'] = false;
    }
}

function authCurrentUser()
{
    authBootstrap();
    if (empty($_SESSION['user_id'])) return null;
    $userId = (int)$_SESSION['user_id'];
    if (!authSessionDeviceIsValid($userId)) {
        authClearLocalSession();
        return null;
    }
    authTouchCurrentDevice($userId);
    return authFindUserById($userId);
}

function authRegister($username, $email, $password)
{
    $username = trim((string)$username);
    $email = authNormalizeEmail($email);
    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) throw new RuntimeException('Username minimal 3 karakter dan hanya boleh huruf, angka, titik, garis bawah, atau tanda minus.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Alamat email tidak valid.');
    if (strlen($password) < 6) throw new RuntimeException('Password minimal 6 karakter.');

    if (authFindUserByUsername($username)) throw new RuntimeException('Username sudah digunakan. Silakan pilih username lain.');
    if (authFindUserByEmail($email)) throw new RuntimeException('Alamat email sudah digunakan oleh akun lain.');

    $usernameKey = strtolower($username);
    $result = authMutateData(function (&$data) use ($username, $usernameKey, $email, $password) {
        foreach (($data['users'] ?? []) as $existingUser) {
            $existingUsername = strtolower(trim((string)($existingUser['username'] ?? '')));
            if ($existingUsername !== '' && $existingUsername === $usernameKey) {
                return ['ok' => false, 'error' => 'Username sudah digunakan. Silakan pilih username lain.'];
            }
            $existingEmail = authNormalizeEmail($existingUser['email'] ?? '');
            if ($existingEmail !== '' && $existingEmail === $email) {
                return ['ok' => false, 'error' => 'Alamat email sudah digunakan oleh akun lain.'];
            }
        }

        $id = (int)$data['meta']['next_user_id']++;
        $user = [
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'email_verified_at' => '',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'pin_hash' => null,
            'device_tokens' => [],
            'role' => $id === 1 ? 'super_admin' : 'user',
            'plan' => 'free',
            'plan_expires_at' => '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        $data['users'][] = $user;
        return ['ok' => true, 'user' => $user];
    });

    if (empty($result['ok']) || empty($result['user'])) {
        throw new RuntimeException((string)($result['error'] ?? 'Registrasi gagal.'));
    }

    $user = $result['user'];
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['pin_verified'] = false;
    authIssueDeviceToken($user['id']);
    return $user;
}

function authLogin($username, $password)
{
    $user = authFindUserByUsername($username);
    if (!$user || !password_verify($password, $user['password_hash'])) throw new RuntimeException('Username atau password salah.');
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['pin_verified'] = empty($user['pin_hash']);
    authIssueDeviceToken($user['id']);
    return $user;
}

function authSetPin($userId, $pin)
{
    if (!preg_match('/^[0-9]{4,6}$/', $pin)) throw new RuntimeException('PIN harus 4 sampai 6 digit angka.');
    authMutateUserData($userId, function (&$data) use ($userId, $pin) {
        foreach ($data['users'] as &$user) {
            if ((int)$user['id'] === (int)$userId) {
                $user['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
                break;
            }
        }
    });
    $_SESSION['pin_verified'] = true;
}

function authVerifyPin($userId, $pin)
{
    $user = authFindUserById($userId);
    if (!$user || empty($user['pin_hash']) || !password_verify($pin, $user['pin_hash'])) throw new RuntimeException('PIN salah.');
    session_regenerate_id(true);
    $_SESSION['pin_verified'] = true;
    $_SESSION['unlock_method'] = 'pin';
}

/**
 * Menandai sesi web sebagai sudah terbuka setelah gate biometrik native aktif.
 *
 * PENTING: fungsi ini tidak memverifikasi sidik jari/wajah. Verifikasi biometrik
 * tetap dilakukan oleh aplikasi native/OS. Controller hanya boleh memanggil
 * fungsi ini untuk trusted-device session dengan nonce satu kali dan user-agent
 * aplikasi resmi. PIN tetap menjadi fallback melalui ?app_lock=1.
 */
function authUnlockAfterNativeBiometricGate($userId)
{
    $userId = (int)$userId;
    if ($userId <= 0) throw new RuntimeException('Akun tidak ditemukan.');
    $current = authCurrentUser();
    if (!$current || (int)($current['id'] ?? 0) !== $userId) {
        throw new RuntimeException('Sesi perangkat tidak valid. Silakan login ulang.');
    }
    session_regenerate_id(true);
    $_SESSION['pin_verified'] = true;
    $_SESSION['unlock_method'] = 'native_biometric';
    $_SESSION['native_biometric_unlocked_at'] = time();
}


/**
 * Mengubah password dari halaman Email & Keamanan.
 * Tidak memakai token email karena pengguna sudah login dan membuka akun dengan PIN.
 * Password saat ini tetap wajib diverifikasi untuk mencegah perubahan oleh pihak lain
 * yang kebetulan memegang sesi/perangkat yang sedang terbuka.
 */
function authChangePassword($userId, $currentPassword, $newPassword)
{
    $userId = (int)$userId;
    $currentPassword = (string)$currentPassword;
    $newPassword = (string)$newPassword;

    if ($userId <= 0) throw new RuntimeException('Akun tidak ditemukan.');
    if ($currentPassword === '') throw new RuntimeException('Password saat ini wajib diisi.');
    if (strlen($newPassword) < 6) throw new RuntimeException('Password baru minimal 6 karakter.');

    $result = authMutateUserData($userId, function (&$data) use ($userId, $currentPassword, $newPassword) {
        foreach ($data['users'] as &$user) {
            if ((int)($user['id'] ?? 0) !== $userId) continue;
            if (empty($user['password_hash']) || !password_verify($currentPassword, (string)$user['password_hash'])) {
                return ['ok' => false, 'error' => 'Password saat ini salah.'];
            }
            if (password_verify($newPassword, (string)$user['password_hash'])) {
                return ['ok' => false, 'error' => 'Password baru tidak boleh sama dengan password saat ini.'];
            }

            $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $user['password_changed_at'] = date('Y-m-d H:i:s');
            // Putuskan trusted-device lama setelah password berubah.
            $user['device_tokens'] = [];
            return ['ok' => true];
        }
        unset($user);
        return ['ok' => false, 'error' => 'Akun tidak ditemukan.'];
    });

    if (empty($result['ok'])) throw new RuntimeException((string)($result['error'] ?? 'Gagal mengubah password.'));

    // Sesi saat ini tetap aktif, tetapi diberi trusted-device token baru.
    session_regenerate_id(true);
    authIssueDeviceToken($userId);
    return true;
}

/**
 * Mengubah PIN dari halaman Email & Keamanan tanpa token email.
 * PIN lama wajib benar, lalu PIN baru disimpan sebagai hash.
 */
function authChangePin($userId, $currentPin, $newPin)
{
    $userId = (int)$userId;
    $currentPin = trim((string)$currentPin);
    $newPin = trim((string)$newPin);

    if ($userId <= 0) throw new RuntimeException('Akun tidak ditemukan.');
    if (!preg_match('/^[0-9]{4,6}$/', $currentPin)) throw new RuntimeException('PIN saat ini harus 4 sampai 6 digit angka.');
    if (!preg_match('/^[0-9]{4,6}$/', $newPin)) throw new RuntimeException('PIN baru harus 4 sampai 6 digit angka.');
    if ($currentPin === $newPin) throw new RuntimeException('PIN baru tidak boleh sama dengan PIN saat ini.');

    $result = authMutateUserData($userId, function (&$data) use ($userId, $currentPin, $newPin) {
        foreach ($data['users'] as &$user) {
            if ((int)($user['id'] ?? 0) !== $userId) continue;
            if (empty($user['pin_hash']) || !password_verify($currentPin, (string)$user['pin_hash'])) {
                return ['ok' => false, 'error' => 'PIN saat ini salah.'];
            }
            $user['pin_hash'] = password_hash($newPin, PASSWORD_DEFAULT);
            $user['pin_changed_at'] = date('Y-m-d H:i:s');
            return ['ok' => true];
        }
        unset($user);
        return ['ok' => false, 'error' => 'Akun tidak ditemukan.'];
    });

    if (empty($result['ok'])) throw new RuntimeException((string)($result['error'] ?? 'Gagal mengubah PIN.'));
    session_regenerate_id(true);
    $_SESSION['pin_verified'] = true;
    return true;
}

function authLogout()
{
    $token = $_COOKIE[DEVICE_COOKIE] ?? '';
    $hash = $token ? hash('sha256', $token) : '';
    $userId = $_SESSION['user_id'] ?? 0;
    if ($hash && $userId) {
        authMutateUserData($userId, function (&$data) use ($userId, $hash) {
            foreach ($data['users'] as &$user) {
                if ((int)$user['id'] === (int)$userId) {
                    $user['device_tokens'] = array_values(array_filter($user['device_tokens'] ?? [], function ($dt) use ($hash) {
                        return empty($dt['hash']) || !hash_equals($dt['hash'], $hash);
                    }));
                    break;
                }
            }
        });
    }
    setcookie(DEVICE_COOKIE, '', time() - 3600, '/', '', false, true);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}


function authResetPasswordWithPin($username, $pin, $newPassword)
{
    $username = strtolower(trim((string)$username));
    $pin = trim((string)$pin);
    $newPassword = (string)$newPassword;

    if ($username === '') throw new RuntimeException('Username wajib diisi.');
    if (!preg_match('/^[0-9]{4,6}$/', $pin)) throw new RuntimeException('PIN harus 4 sampai 6 digit angka.');
    if (strlen($newPassword) < 6) throw new RuntimeException('Password baru minimal 6 karakter.');

    $result = authMutateData(function (&$data) use ($username, $pin, $newPassword) {
        $now = time();
        foreach ($data['users'] as &$user) {
            if (strtolower((string)($user['username'] ?? '')) !== $username) continue;
            $lockedUntil = !empty($user['password_reset_locked_until']) ? strtotime((string)$user['password_reset_locked_until']) : 0;
            if ($lockedUntil && $lockedUntil > $now) {
                $minutes = max(1, (int)ceil(($lockedUntil - $now) / 60));
                return ['ok' => false, 'error' => 'Terlalu banyak percobaan. Coba lagi sekitar ' . $minutes . ' menit.'];
            }
            if (empty($user['pin_hash']) || !password_verify($pin, $user['pin_hash'])) {
                $attempts = (int)($user['password_reset_attempts'] ?? 0) + 1;
                if ($attempts >= 5) {
                    $user['password_reset_attempts'] = 0;
                    $user['password_reset_locked_until'] = date('Y-m-d H:i:s', $now + 900);
                    return ['ok' => false, 'error' => 'Username atau PIN tidak cocok. Reset password dikunci selama 15 menit.'];
                }
                $user['password_reset_attempts'] = $attempts;
                $user['password_reset_locked_until'] = '';
                return ['ok' => false, 'error' => 'Username atau PIN tidak cocok.'];
            }
            $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $user['password_reset_attempts'] = 0;
            $user['password_reset_locked_until'] = '';
            $user['device_tokens'] = [];
            return ['ok' => true, 'user_id' => (int)$user['id']];
        }
        unset($user);
        return ['ok' => false, 'error' => 'Username atau PIN tidak cocok.'];
    });
    if (empty($result['ok'])) throw new RuntimeException((string)($result['error'] ?? 'Reset password gagal.'));
    return true;
}


function authAssertEmailUnique($email, $exceptUserId = 0)
{
    $email = authNormalizeEmail($email);
    $data = authReadData();
    foreach (($data['users'] ?? []) as $u) {
        if ((int)($u['id'] ?? 0) === (int)$exceptUserId) continue;
        if (authNormalizeEmail($u['email'] ?? '') === $email) {
            throw new RuntimeException('Alamat email sudah digunakan oleh akun lain.');
        }
    }
}

function authUpdateEmail($userId, $email)
{
    $email = authNormalizeEmail($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Alamat email tidak valid.');
    authAssertEmailUnique($email, $userId);

    return authMutateUserData($userId, function (&$data) use ($userId, $email) {
        foreach ($data['users'] as &$user) {
            if ((int)($user['id'] ?? 0) !== (int)$userId) continue;
            $old = authNormalizeEmail($user['email'] ?? '');
            if ($old === $email) return $user;

            $user['email'] = $email;
            $user['email_verified_at'] = '';
            unset(
                $user['email_verify_token_hash'],
                $user['email_verify_token_expires_at'],
                $user['email_verify_token_sent_at'],
                $user['email_verify_token_attempts'],
                $user['recovery_token_hash'],
                $user['recovery_token_expires_at'],
                $user['recovery_token_sent_at'],
                $user['recovery_token_attempts']
            );
            return $user;
        }
        unset($user);
        throw new RuntimeException('Akun tidak ditemukan.');
    });
}

function authRandomEmailCode()
{
    return (string)random_int(100000, 999999);
}

function authTokenRateLimitSeconds($sentAt, $seconds = 60)
{
    if (!$sentAt) return 0;
    $ts = strtotime((string)$sentAt);
    if (!$ts) return 0;
    return max(0, $seconds - (time() - $ts));
}

function authRequestEmailVerification($userId)
{
    $user = authFindUserById($userId);
    if (!$user) throw new RuntimeException('Akun tidak ditemukan.');
    $email = authNormalizeEmail($user['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Lengkapi alamat email terlebih dahulu.');
    if (!empty($user['email_verified_at'])) return ['already_verified' => true, 'masked' => authMaskEmail($email)];

    $wait = authTokenRateLimitSeconds($user['email_verify_token_sent_at'] ?? '', 60);
    if ($wait > 0) throw new RuntimeException('Tunggu ' . $wait . ' detik sebelum mengirim ulang kode verifikasi.');

    $code = authRandomEmailCode();
    $hash = hash('sha256', $code);
    $expiresAt = date('Y-m-d H:i:s', time() + 900);

    authMutateUserData($userId, function (&$data) use ($userId, $hash, $expiresAt) {
        foreach ($data['users'] as &$u) {
            if ((int)($u['id'] ?? 0) !== (int)$userId) continue;
            $u['email_verify_token_hash'] = $hash;
            $u['email_verify_token_expires_at'] = $expiresAt;
            $u['email_verify_token_sent_at'] = date('Y-m-d H:i:s');
            $u['email_verify_token_attempts'] = 0;
            return;
        }
        unset($u);
    });

    try {
        $html = financeSecurityEmailHtml(
            'Verifikasi email',
            'Gunakan kode berikut untuk memverifikasi alamat email pada akun ' . $user['username'] . '.',
            $code,
            15
        );
        financeSendMail($email, 'Kode verifikasi email - ' . APP_NAME, $html, 'Kode verifikasi email Anda: ' . $code . '. Berlaku 15 menit.');
    } catch (Throwable $e) {
        authMutateUserData($userId, function (&$data) use ($userId, $hash) {
            foreach ($data['users'] as &$u) {
                if ((int)($u['id'] ?? 0) !== (int)$userId) continue;
                if (($u['email_verify_token_hash'] ?? '') === $hash) {
                    unset($u['email_verify_token_hash'], $u['email_verify_token_expires_at'], $u['email_verify_token_sent_at'], $u['email_verify_token_attempts']);
                }
                return;
            }
            unset($u);
        });
        throw $e;
    }

    return ['sent' => true, 'masked' => authMaskEmail($email), 'expires_at' => $expiresAt];
}

function authVerifyEmailToken($userId, $code)
{
    $code = trim((string)$code);
    if (!preg_match('/^\d{6}$/', $code)) throw new RuntimeException('Kode verifikasi harus 6 digit.');
    $hash = hash('sha256', $code);

    $result = authMutateUserData($userId, function (&$data) use ($userId, $hash) {
        foreach ($data['users'] as &$u) {
            if ((int)($u['id'] ?? 0) !== (int)$userId) continue;
            if (empty($u['email_verify_token_hash']) || empty($u['email_verify_token_expires_at'])) {
                return ['ok' => false, 'error' => 'Kode verifikasi belum diminta atau sudah tidak berlaku.'];
            }
            if (strtotime((string)$u['email_verify_token_expires_at']) < time()) {
                unset($u['email_verify_token_hash'], $u['email_verify_token_expires_at'], $u['email_verify_token_sent_at'], $u['email_verify_token_attempts']);
                return ['ok' => false, 'error' => 'Kode verifikasi sudah kedaluwarsa. Minta kode baru.'];
            }
            if (!hash_equals((string)$u['email_verify_token_hash'], $hash)) {
                $attempts = (int)($u['email_verify_token_attempts'] ?? 0) + 1;
                $u['email_verify_token_attempts'] = $attempts;
                if ($attempts >= 5) {
                    unset($u['email_verify_token_hash'], $u['email_verify_token_expires_at'], $u['email_verify_token_sent_at'], $u['email_verify_token_attempts']);
                    return ['ok' => false, 'error' => 'Terlalu banyak kode salah. Minta kode verifikasi baru.'];
                }
                return ['ok' => false, 'error' => 'Kode verifikasi salah.'];
            }

            $u['email_verified_at'] = date('Y-m-d H:i:s');
            unset($u['email_verify_token_hash'], $u['email_verify_token_expires_at'], $u['email_verify_token_sent_at'], $u['email_verify_token_attempts']);
            return ['ok' => true, 'email' => $u['email'] ?? ''];
        }
        unset($u);
        return ['ok' => false, 'error' => 'Akun tidak ditemukan.'];
    });

    if (empty($result['ok'])) throw new RuntimeException((string)($result['error'] ?? 'Verifikasi email gagal.'));
    return $result;
}

function authRequestRecoveryToken($identifier)
{
    $identifier = trim((string)$identifier);
    if ($identifier === '') throw new RuntimeException('Masukkan username atau email.');
    $user = authFindUserByIdentifier($identifier);
    if (!$user) throw new RuntimeException('Akun tidak ditemukan.');
    $email = authNormalizeEmail($user['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Akun ini belum memiliki email pemulihan. Login lalu lengkapi email, atau hubungi admin.');
    }
    if (empty($user['email_verified_at'])) {
        throw new RuntimeException('Email akun belum diverifikasi. Login lalu verifikasi email terlebih dahulu.');
    }

    $wait = authTokenRateLimitSeconds($user['recovery_token_sent_at'] ?? '', 60);
    if ($wait > 0) throw new RuntimeException('Tunggu ' . $wait . ' detik sebelum meminta token pemulihan lagi.');

    $code = authRandomEmailCode();
    $hash = hash('sha256', $code);
    $expiresAt = date('Y-m-d H:i:s', time() + 900);
    $userId = (int)$user['id'];

    authMutateUserData($userId, function (&$data) use ($userId, $hash, $expiresAt) {
        foreach ($data['users'] as &$u) {
            if ((int)($u['id'] ?? 0) !== $userId) continue;
            $u['recovery_token_hash'] = $hash;
            $u['recovery_token_expires_at'] = $expiresAt;
            $u['recovery_token_sent_at'] = date('Y-m-d H:i:s');
            $u['recovery_token_attempts'] = 0;
            return;
        }
        unset($u);
    });

    try {
        $html = financeSecurityEmailHtml(
            'Token pemulihan akun',
            'Token ini dapat digunakan untuk mereset password dan/atau PIN akun ' . $user['username'] . '.',
            $code,
            15
        );
        financeSendMail($email, 'Token reset password/PIN - ' . APP_NAME, $html, 'Token reset password/PIN Anda: ' . $code . '. Berlaku 15 menit.');
    } catch (Throwable $e) {
        authMutateUserData($userId, function (&$data) use ($userId, $hash) {
            foreach ($data['users'] as &$u) {
                if ((int)($u['id'] ?? 0) !== $userId) continue;
                if (($u['recovery_token_hash'] ?? '') === $hash) {
                    unset($u['recovery_token_hash'], $u['recovery_token_expires_at'], $u['recovery_token_sent_at'], $u['recovery_token_attempts']);
                }
                return;
            }
            unset($u);
        });
        throw $e;
    }

    return ['sent' => true, 'masked' => authMaskEmail($email), 'expires_at' => $expiresAt, 'identifier' => $identifier];
}

function authResetWithEmailToken($identifier, $code, $newPassword = '', $newPin = '')
{
    $identifier = trim((string)$identifier);
    $code = trim((string)$code);
    $newPassword = (string)$newPassword;
    $newPin = trim((string)$newPin);

    if ($identifier === '') throw new RuntimeException('Username atau email wajib diisi.');
    if (!preg_match('/^\d{6}$/', $code)) throw new RuntimeException('Token harus 6 digit.');
    if ($newPassword === '' && $newPin === '') throw new RuntimeException('Isi password baru dan/atau PIN baru.');
    if ($newPassword !== '' && strlen($newPassword) < 6) throw new RuntimeException('Password baru minimal 6 karakter.');
    if ($newPin !== '' && !preg_match('/^[0-9]{4,6}$/', $newPin)) throw new RuntimeException('PIN baru harus 4 sampai 6 digit angka.');

    $identifierLower = strtolower($identifier);
    $codeHash = hash('sha256', $code);
    $result = authMutateData(function (&$data) use ($identifierLower, $codeHash, $newPassword, $newPin) {
        foreach ($data['users'] as &$u) {
            $matches = strtolower((string)($u['username'] ?? '')) === $identifierLower
                || authNormalizeEmail($u['email'] ?? '') === authNormalizeEmail($identifierLower);
            if (!$matches) continue;

            if (empty($u['email_verified_at'])) return ['ok' => false, 'error' => 'Email akun belum diverifikasi.'];
            if (empty($u['recovery_token_hash']) || empty($u['recovery_token_expires_at'])) {
                return ['ok' => false, 'error' => 'Token pemulihan belum diminta atau sudah tidak berlaku.'];
            }
            if (strtotime((string)$u['recovery_token_expires_at']) < time()) {
                unset($u['recovery_token_hash'], $u['recovery_token_expires_at'], $u['recovery_token_sent_at'], $u['recovery_token_attempts']);
                return ['ok' => false, 'error' => 'Token pemulihan sudah kedaluwarsa. Minta token baru.'];
            }
            if (!hash_equals((string)$u['recovery_token_hash'], $codeHash)) {
                $attempts = (int)($u['recovery_token_attempts'] ?? 0) + 1;
                $u['recovery_token_attempts'] = $attempts;
                if ($attempts >= 5) {
                    unset($u['recovery_token_hash'], $u['recovery_token_expires_at'], $u['recovery_token_sent_at'], $u['recovery_token_attempts']);
                    return ['ok' => false, 'error' => 'Terlalu banyak token salah. Minta token pemulihan baru.'];
                }
                return ['ok' => false, 'error' => 'Token pemulihan salah.'];
            }

            if ($newPassword !== '') $u['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($newPin !== '') $u['pin_hash'] = password_hash($newPin, PASSWORD_DEFAULT);
            $u['device_tokens'] = [];
            $u['password_reset_attempts'] = 0;
            $u['password_reset_locked_until'] = '';
            unset($u['recovery_token_hash'], $u['recovery_token_expires_at'], $u['recovery_token_sent_at'], $u['recovery_token_attempts']);
            return ['ok' => true, 'user_id' => (int)$u['id'], 'password_changed' => $newPassword !== '', 'pin_changed' => $newPin !== ''];
        }
        unset($u);
        return ['ok' => false, 'error' => 'Akun tidak ditemukan.'];
    });

    if (empty($result['ok'])) throw new RuntimeException((string)($result['error'] ?? 'Pemulihan akun gagal.'));
    return $result;
}

function authRequireUnlocked()
{
    $user = authCurrentUser();
    if (!$user || empty($_SESSION['pin_verified'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Sesi terkunci. Silakan masuk kembali.']);
        exit;
    }
    return $user;
}


function authUserRole($user = null)
{
    if (!$user) $user = authCurrentUser();
    if (!$user) return 'guest';

    $role = strtolower(trim((string)($user['role'] ?? 'user')));
    return $role === 'super_admin' ? 'super_admin' : 'user';
}

function authIsSuperAdmin($user = null)
{
    return authUserRole($user) === 'super_admin';
}

// Alias kompatibilitas. Fungsi lama sekarang juga hanya true untuk Super Admin.
function authIsAdmin($user = null)
{
    return authIsSuperAdmin($user);
}
function authTrialStatus($user = null, $includeCountdown = true)
{
    if (!$user) $user = authCurrentUser();
    if (!$user) {
        return [
            'eligible' => false,
            'used' => false,
            'active' => false,
            'started_at' => '',
            'expires_at' => '',
            'confirmed_at' => '',
            'remaining_seconds' => 0,
            'remaining_days' => 0,
            'expired' => false,
        ];
    }

    $startedAt = trim((string)($user['premium_trial_started_at'] ?? ''));
    $expiresAt = trim((string)($user['premium_trial_expires_at'] ?? ''));
    $confirmedAt = trim((string)($user['premium_trial_confirmed_at'] ?? ''));
    $usedAt = trim((string)($user['premium_trial_used_at'] ?? ''));
    $cancelledAt = trim((string)($user['premium_trial_cancelled_at'] ?? ''));
    $used = $usedAt !== '' || $startedAt !== '';

    $expireTs = $expiresAt !== '' ? strtotime($expiresAt) : false;
    $active = $used && $cancelledAt === '' && $expireTs !== false && $expireTs > time();
    $remainingSeconds = $active ? max(0, $expireTs - time()) : 0;
    $remainingDays = $active ? max(1, (int)ceil($remainingSeconds / 86400)) : 0;
    $expired = $used && !$active && $expiresAt !== '' && $expireTs !== false && $expireTs <= time();

    // Trial hanya untuk akun yang belum pernah memakai trial dan belum pernah memiliki Premium berbayar/manual.
    $storedPlan = strtolower(trim((string)($user['plan'] ?? 'free')));
    $paidExpiry = trim((string)($user['plan_expires_at'] ?? ''));
    $paidExpiryTs = $paidExpiry !== '' ? strtotime($paidExpiry) : false;
    $paidActive = $storedPlan === 'premium' && ($paidExpiry === '' || ($paidExpiryTs !== false && $paidExpiryTs >= strtotime('today')));
    $everPremium = $storedPlan === 'premium'
        || trim((string)($user['premium_last_invoice'] ?? '')) !== ''
        || (trim((string)($user['premium_type'] ?? '')) !== '' && trim((string)($user['premium_type'] ?? '')) !== 'trial');

    $eligible = !authIsSuperAdmin($user) && !$used && !$paidActive && !$everPremium;

    $status = [
        'eligible' => $eligible,
        'used' => $used,
        'active' => $active,
        'started_at' => $startedAt,
        'expires_at' => $expiresAt,
        'confirmed_at' => $confirmedAt,
        'expired' => $expired,
        'cancelled' => $cancelledAt !== '',
    ];
    if ($includeCountdown) {
        $status['remaining_seconds'] = $remainingSeconds;
        $status['remaining_days'] = $remainingDays;
    }
    return $status;
}

function authPlan($user = null)
{
    if (!$user) $user = authCurrentUser();
    if (!$user) {
        return [
            'plan' => 'free',
            'expires_at' => '',
            'active' => false,
            'source' => 'free',
            'paid' => false,
            'trial' => authTrialStatus(null),
        ];
    }

    $storedPlan = strtolower(trim((string)($user['plan'] ?? 'free')));
    $paidExpiry = trim((string)($user['plan_expires_at'] ?? ''));
    $paidExpiryTs = $paidExpiry !== '' ? strtotime($paidExpiry) : false;
    $paidActive = $storedPlan === 'premium' && ($paidExpiry === '' || ($paidExpiryTs !== false && $paidExpiryTs >= strtotime('today')));
    $trial = authTrialStatus($user, false);

    if ($paidActive) {
        return [
            'plan' => 'premium',
            'expires_at' => $paidExpiry,
            'active' => true,
            'source' => 'paid',
            'paid' => true,
            'trial' => $trial,
        ];
    }

    if (!empty($trial['active'])) {
        return [
            'plan' => 'premium',
            'expires_at' => (string)$trial['expires_at'],
            'active' => true,
            'source' => 'trial',
            'paid' => false,
            'trial' => $trial,
        ];
    }

    return [
        'plan' => 'free',
        'expires_at' => '',
        'active' => false,
        'source' => 'free',
        'paid' => false,
        'trial' => $trial,
    ];
}

/**
 * Hak akses Premium terpusat.
 * Super Admin selalu memiliki akses penuh tanpa memerlukan paket.
 */
function authHasPremiumAccess($user = null)
{
    if (!$user) $user = authCurrentUser();
    if (!$user) return false;
    if (authIsSuperAdmin($user)) return true;
    $plan = authPlan($user);
    return !empty($plan['active']);
}

/**
 * Proteksi endpoint JSON khusus fitur Premium.
 * Endpoint basic (mis. edit transaksi) tidak perlu memanggil fungsi ini.
 */
function authRequirePremiumJson($message = 'Fitur ini tersedia untuk akun Premium.')
{
    $user = authRequireUnlocked();
    if (authHasPremiumAccess($user)) return $user;

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => (string)$message,
        'premium_required' => true
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Proteksi endpoint file/download Premium (PDF, CSV, Excel, backup).
 */
function authRequirePremiumDownload($message = 'Fitur download ini tersedia untuk akun Premium.')
{
    $user = authRequireUnlocked();
    if (authHasPremiumAccess($user)) return $user;

    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo (string)$message;
    exit;
}
function authAdminSetUserPlan($userId, $plan, $expires = '')
{
    if (!authIsSuperAdmin()) throw new RuntimeException('Akses Super Admin diperlukan.');
    $plan = in_array($plan, ['free', 'premium'], true) ? $plan : 'free';
    if ($expires !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) throw new InvalidArgumentException('Tanggal masa aktif tidak valid.');
    return authMutateUserData($userId, function (&$data) use ($userId, $plan, $expires) {
        foreach ($data['users'] as &$u) if ((int)$u['id'] === (int)$userId) {
            $u['plan'] = $plan;
            $u['plan_expires_at'] = $plan === 'premium' ? $expires : '';
            if ($plan === 'premium') {
                if (trim((string)($u['premium_type'] ?? '')) === '') $u['premium_type'] = 'manual';
                $u['premium_manual_granted_at'] = date('Y-m-d H:i:s');
            }
            if ($plan === 'free' && !empty(authTrialStatus($u)['active'])) {
                $u['premium_trial_expires_at'] = date('Y-m-d H:i:s');
                $u['premium_trial_cancelled_at'] = date('Y-m-d H:i:s');
            }
            return $u;
        }
        unset($u);
        throw new InvalidArgumentException('User tidak ditemukan.');
    });
}
