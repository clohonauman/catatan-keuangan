<?php
namespace app\services;

/** Maps a cryptographically verified Google subject to the existing account model. */
final class GoogleLoginService
{
    public static function login(array $claims): array
    {
        if (!function_exists('authReadData')) throw new \RuntimeException('Modul autentikasi belum siap.');
        $sub = trim((string)($claims['sub'] ?? ''));
        $email = strtolower(trim((string)($claims['email'] ?? '')));
        $emailVerified = ($claims['email_verified'] ?? false) === true
            || strtolower((string)($claims['email_verified'] ?? '')) === 'true';
        if ($sub === '' || strlen($sub) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$emailVerified) {
            throw new \RuntimeException('Akun Google belum terverifikasi.');
        }
        $claims['email_verified'] = true;

        $all = authReadData();
        $users = (array)($all['users'] ?? []);
        $user = null;

        // Prefer a stable Google subject. Email alone is never used as the sole identity key.
        foreach ($users as $candidate) {
            if (hash_equals((string)($candidate['google_sub'] ?? ''), $sub)) {
                $user = $candidate;
                break;
            }
        }

        if (!$user) {
            $existing = authFindUserByEmail($email);
            if ($existing) {
                $user = self::linkExistingAccount($existing, $sub, $email, $claims);
            }
        }

        if (!$user) {
            $username = self::availableUsername($email, $users);
            $randomPassword = bin2hex(random_bytes(32));
            $createdUser = null;
            try {
                $createdUser = authRegister($username, $email, $randomPassword);
            } catch (\Throwable $e) {
                // Another request may have registered this address after the first lookup.
                // Re-run the exact existing-account linking policy; never attach blindly.
                $racedUser = authFindUserByEmail($email);
                if (!$racedUser) throw $e;
                $user = self::linkExistingAccount($racedUser, $sub, $email, $claims);
            }
            if ($createdUser) {
                // This account was just created based on a valid Google ID token, so the
                // identity is being attached to its own new record, not to an old account.
                self::attachIdentity((int)$createdUser['id'], $sub, $email, $claims, true);
                $user = authFindUserById((int)$createdUser['id']);
            }
        }

        if (!$user) throw new \RuntimeException('Akun Catatan Keuangan tidak berhasil disiapkan.');
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        // Match the existing login policy: PIN-protected users must unlock; new users
        // without a PIN are routed to the mandatory PIN creation screen by the view.
        $_SESSION['pin_verified'] = empty($user['pin_hash']);
        authIssueDeviceToken((int)$user['id']);
        $fresh = authFindUserById((int)$user['id']) ?: $user;
        authSendLoginNotification($fresh);
        return $fresh;
    }

    private static function linkExistingAccount(array $user, string $sub, string $email, array $claims): array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) throw new \RuntimeException('Akun Catatan Keuangan tidak valid.');
        $linkedSub = trim((string)($user['google_sub'] ?? ''));
        if ($linkedSub !== '' && !hash_equals($linkedSub, $sub)) {
            throw new \RuntimeException('Email ini sudah terhubung ke akun Google lain. Login memakai metode yang terhubung sebelumnya.');
        }
        if ($linkedSub === '') {
            $domain = strtolower((string)substr(strrchr($email, '@') ?: '', 1));
            $hostedDomain = strtolower(trim((string)($claims['hd'] ?? '')));
            // email_verified is not always authoritative for third-party addresses.
            // Only auto-link Gmail or a matching Google Workspace domain.
            $googleAuthoritative = $domain === 'gmail.com' || ($hostedDomain !== '' && hash_equals($domain, $hostedDomain));
            if (!$googleAuthoritative) {
                throw new \RuntimeException('Demi keamanan, akun lama dengan email non-Gmail perlu masuk menggunakan username/password dan menghubungkan Google melalui bantuan administrator.');
            }
        }
        self::attachIdentity($userId, $sub, $email, $claims, false);
        $fresh = authFindUserById($userId);
        if (!$fresh) throw new \RuntimeException('Akun Catatan Keuangan tidak ditemukan setelah penautan Google.');
        return $fresh;
    }

    private static function attachIdentity(int $userId, string $sub, string $email, array $claims, bool $newAccount): void
    {
        authMutateUserData($userId, static function (&$data) use ($sub, $email, $claims, $newAccount) {
            if (empty($data['users'][0]) || !is_array($data['users'][0])) {
                throw new \RuntimeException('Akun tidak ditemukan saat menautkan Google.');
            }
            $row =& $data['users'][0];
            $currentSub = trim((string)($row['google_sub'] ?? ''));
            if ($currentSub !== '' && !hash_equals($currentSub, $sub)) {
                throw new \RuntimeException('Akun ini sudah terhubung ke akun Google lain.');
            }
            $currentEmail = strtolower(trim((string)($row['email'] ?? '')));
            if (!$newAccount && $currentEmail !== $email) {
                throw new \RuntimeException('Email akun berubah saat proses login. Silakan coba lagi.');
            }
            $row['google_sub'] = $sub;
            $row['google_name'] = trim((string)($claims['name'] ?? ''));
            $row['google_picture'] = self::safePicture((string)($claims['picture'] ?? ''));
            $row['google_email'] = $email;
            if ($newAccount || empty($row['email_verified_at'])) {
                $row['email_verified_at'] = date('Y-m-d H:i:s');
            }
            unset($row);
        });
    }

    private static function availableUsername(string $email, array $users): string
    {
        $local = strtolower((string)strstr($email, '@', true));
        $base = preg_replace('/[^a-z0-9_.-]+/', '', $local) ?: 'googleuser';
        $base = trim($base, '._-');
        if (strlen($base) < 3) $base = 'googleuser';
        $base = substr($base, 0, 22);
        $existing = [];
        foreach ($users as $user) $existing[strtolower((string)($user['username'] ?? ''))] = true;
        $candidate = $base;
        $n = 1;
        while (isset($existing[strtolower($candidate)]) || authFindUserByUsername($candidate)) {
            $suffix = (string)$n++;
            $candidate = substr($base, 0, max(3, 29 - strlen($suffix))) . $suffix;
            if ($n > 10000) throw new \RuntimeException('Username otomatis tidak dapat dibuat. Hubungi administrator.');
        }
        return $candidate;
    }

    private static function safePicture(string $picture): string
    {
        if ($picture === '' || strlen($picture) > 1000) return '';
        $parts = parse_url($picture);
        return is_array($parts) && strtolower((string)($parts['scheme'] ?? '')) === 'https' ? $picture : '';
    }
}
