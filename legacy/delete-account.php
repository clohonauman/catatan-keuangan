<?php
require_once __DIR__ . '/account_deletion_helper.php';
$error = '';
$success = false;
$user = authCurrentUser();
if (empty($_SESSION['delete_account_csrf'])) $_SESSION['delete_account_csrf'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['delete_account_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals((string)($_SESSION['delete_account_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        if (empty($_POST['confirm_delete'])) throw new RuntimeException('Centang konfirmasi penghapusan akun.');
        $username = trim((string)($_POST['username'] ?? ''));
        $secretType = (string)($_POST['secret_type'] ?? 'password');
        $secret = (string)($_POST['secret'] ?? '');
        cfDeleteAccountVerifyAndDelete($username, $secret, $secretType);
        $success = true;
        $user = null;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
function eh($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Hapus Akun - Catatan Keuangan</title>
    <link rel="icon" type="image/webp" href="assets/icon.webp">
    <style>
        :root {
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #172033;
            background: #f5f7fb
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0
        }

        .wrap {
            max-width: 720px;
            margin: 0 auto;
            padding: 34px 18px 60px
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px
        }

        .brand img {
            width: 52px;
            height: 52px;
            border-radius: 14px
        }

        .card {
            background: #fff;
            border: 1px solid #e5eaf2;
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 16px 45px rgba(32, 57, 93, .08)
        }

        h1 {
            margin: 0 0 10px;
            font-size: 28px
        }

        .muted {
            color: #6d788c;
            line-height: 1.65
        }

        .warning {
            background: #fff3f1;
            border: 1px solid #ffd2cc;
            color: #8d281b;
            padding: 14px 16px;
            border-radius: 14px;
            margin: 18px 0
        }

        .alert {
            padding: 12px 14px;
            border-radius: 12px;
            margin: 14px 0
        }

        .error {
            background: #fff0f0;
            color: #a41d1d
        }

        .ok {
            background: #edf9f1;
            color: #126c39
        }

        label {
            display: block;
            font-weight: 700;
            margin: 15px 0 6px
        }

        input,
        select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #d9e0ea;
            border-radius: 12px;
            font: inherit;
            background: #fff
        }

        .delete-account-form { display:block !important; margin-top:18px }

        .delete-account-form[hidden] { display:block !important }

        .check {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            font-weight: 500
        }

        .check input {
            width: auto;
            margin-top: 4px
        }

        .btn {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 14px 16px;
            font-weight: 800;
            font-size: 15px;
            cursor: pointer
        }

        .danger {
            background: #c62f25;
            color: #fff
        }

        .links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px
        }

        .links a {
            color: #175cd3;
            text-decoration: none;
            font-weight: 700
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
                font-size: 24px
            }
        }
    </style>
</head>

<body>
    <main class="wrap">
        <div class="brand"><img src="assets/icon.webp" alt="Catatan Keuangan">
            <div><b>Catatan Keuangan</b>
                <div class="muted">Kontrol akun & data</div>
            </div>
        </div>
        <section class="card">
            <?php if ($success): ?>
                <h1>Akun berhasil dihapus</h1>
                <div class="alert ok">Akun dan data keuangan terkait telah dihapus dari server Catatan Keuangan.</div>
                <p class="muted">Data offline yang masih tersimpan pada perangkat dapat dihapus dengan membersihkan data
                    aplikasi/browser atau menghapus aplikasi.</p>
                <div class="links"><a href="index.php">Kembali ke Catatan Keuangan</a><a href="privacy-policy.php">Kebijakan
                        Privasi</a></div>
            <?php else: ?>
                <h1>Hapus akun Catatan Keuangan</h1>
                <p class="muted">Halaman ini dapat digunakan dari aplikasi maupun browser tanpa harus memasang ulang
                    aplikasi.</p>
                <div class="warning"><b>Penghapusan bersifat permanen.</b><br>Transaksi, chat, budget, target, pengaturan,
                    foto nota, token perangkat, data pembelian Premium, invoice, bukti pembayaran, dan data akun di server akan dihapus.</div>
                <?php if ($error): ?><div class="alert error"><?= eh($error) ?></div><?php endif; ?>
                <form method="post" action="delete-account.php" autocomplete="off" class="delete-account-form">
                    <input type="hidden" name="csrf" value="<?= eh($csrf) ?>">
                    <div class="muted" style="margin-bottom:12px">Verifikasi identitas akun sebelum penghapusan diproses.</div>
                    <label>Username</label>
                    <input name="username" required minlength="3" maxlength="30" value="<?= eh($user['username'] ?? '') ?>"
                        <?= $user ? 'readonly' : '' ?>>
                    <label>Verifikasi menggunakan</label>
                    <select name="secret_type">
                        <option value="password">Password</option>
                        <option value="pin">PIN</option>
                    </select>
                    <label>Password / PIN</label>
                    <input type="password" name="secret" required autocomplete="current-password">
                    <label class="check"><input type="checkbox" name="confirm_delete" value="1" required><span>Saya memahami
                            bahwa akun dan data saya akan dihapus secara permanen.</span></label>
                    <div style="height:14px"></div><button class="btn danger" type="submit">Hapus akun dan data
                        saya</button>
                </form>
                <div class="links"><a href="index.php">Batal / kembali</a><a href="privacy-policy.php">Baca Kebijakan
                        Privasi</a></div>
            <?php endif; ?>
        </section>
    </main>
</body>

</html>