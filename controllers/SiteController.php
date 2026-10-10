<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller
{
    public $layout = false;

    public function actionIndex()
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        require_once Yii::getAlias('@app/legacy/db.php');
        require_once Yii::getAlias('@app/legacy/maintenance_helper.php');

        $maintenance = maintenanceSnapshot();
        $maintenanceUser = authCurrentUser();
        // V44: halaman login/registrasi tetap selalu dapat diakses saat maintenance.
        // Maintenance baru mengunci aplikasi setelah sebuah akun benar-benar login.
        $incomingAction = Yii::$app->request->isPost ? (string)Yii::$app->request->post('action','') : '';
        $maintenanceAuthPost = in_array($incomingAction, ['login','google_login','logout','register','request_recovery','reset_with_email_token'], true);
        if (
            !empty($maintenance['is_active'])
            && $maintenanceUser
            && !maintenanceCanAccess($maintenanceUser, $maintenance)
            && !$maintenanceAuthPost
        ) {
            Yii::$app->response->statusCode = 503;
            Yii::$app->response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            return $this->render('maintenance', ['maintenance'=>$maintenance, 'user'=>$maintenanceUser]);
        }

        $error = '';
        $notice = (Yii::$app->request->get('reset') === 'success')
            ? 'Password/PIN berhasil diperbarui. Silakan login kembali.'
            : ((Yii::$app->request->get('device_logout') === '1') ? 'Perangkat ini telah dikeluarkan dari akun. Silakan login kembali jika ingin masuk lagi.' : '');
        if (!empty($_SESSION['flash_notice'])) { $notice=(string)$_SESSION['flash_notice']; unset($_SESSION['flash_notice']); }
        if (!empty($_SESSION['flash_error'])) { $error=(string)$_SESSION['flash_error']; unset($_SESSION['flash_error']); }
        $mode=(string)Yii::$app->request->get('mode','login');

        if (Yii::$app->request->isPost) {
            $action=(string)Yii::$app->request->post('action','');
            try {
                if ($action === 'google_login') {
                    if (authCurrentUser()) throw new \RuntimeException('Anda sudah login. Logout terlebih dahulu jika ingin berganti akun.');
                    $googleClientId = trim((string)($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: ''));
                    $credential = trim((string)Yii::$app->request->post('google_id_token', Yii::$app->request->post('credential', '')));
                    $expectedNonce = (string)($_SESSION['google_login_nonce'] ?? '');
                    unset($_SESSION['google_login_nonce']);
                    if ($googleClientId === '') throw new \RuntimeException('Login Google belum dikonfigurasi. Silakan gunakan username dan password.');
                    $claims = \app\services\GoogleIdentityService::verifyIdToken($credential, $googleClientId, $expectedNonce);
                    \app\services\GoogleIdentityService::loginExistingAccount($claims);
                    return $this->redirect(['/app-portal/index']);
                }
                if ($action === 'set_preferred_app') {
                    $user = authCurrentUser();
                    if (!$user || empty($_SESSION['pin_verified'])) throw new \RuntimeException('Login dan buka kunci akun terlebih dahulu.');
                    $slug = trim((string)Yii::$app->request->post('app', ''));
                    return $this->redirect(['site/launch-app', 'slug' => $slug]);
                }
                if ($action === 'register') {
                    $newUser=authRegister(Yii::$app->request->post('username',''),Yii::$app->request->post('email',''),Yii::$app->request->post('password',''));
                    try {$verification=authRequestEmailVerification((int)$newUser['id']);$_SESSION['flash_notice']='Akun berhasil dibuat. Kode verifikasi telah dikirim ke '.$verification['masked'].'.';}
                    catch(\Throwable $e){$_SESSION['flash_notice']='Akun berhasil dibuat dan email sudah tersimpan. Kode verifikasi belum dapat dikirim: '.$e->getMessage();}
                    return $this->redirect(['site/index']);
                }
                if ($action === 'login') { authLogin(Yii::$app->request->post('username',''),Yii::$app->request->post('password','')); return $this->redirect(['/app-portal/index']); }
                if ($action === 'request_recovery') { $identifier=trim((string)Yii::$app->request->post('identifier',''));$result=authRequestRecoveryToken($identifier);$_SESSION['recovery_identifier']=$identifier;$_SESSION['flash_notice']='Token pemulihan telah dikirim ke '.$result['masked'].'. Token berlaku 15 menit.';return $this->redirect(['site/index','mode'=>'forgot','sent'=>1]); }
                if ($action === 'reset_with_email_token') {
                    if (Yii::$app->request->post('password','') !== Yii::$app->request->post('password_confirm','')) throw new \RuntimeException('Konfirmasi password baru tidak sama.');
                    if (Yii::$app->request->post('pin','') !== Yii::$app->request->post('pin_confirm','')) throw new \RuntimeException('Konfirmasi PIN baru tidak sama.');
                    authResetWithEmailToken(Yii::$app->request->post('identifier',$_SESSION['recovery_identifier']??''),Yii::$app->request->post('token',''),Yii::$app->request->post('password',''),Yii::$app->request->post('pin',''));
                    unset($_SESSION['recovery_identifier']); return $this->redirect(['site/index','reset'=>'success']);
                }
                if ($action === 'set_pin') { $user=authCurrentUser();if(!$user)throw new \RuntimeException('Sesi login tidak ditemukan.');if(Yii::$app->request->post('pin','')!==Yii::$app->request->post('pin_confirm',''))throw new \RuntimeException('Konfirmasi PIN tidak sama.');authSetPin($user['id'],Yii::$app->request->post('pin',''));return $this->redirect(['/app-portal/index']); }
                if ($action === 'unlock') { $user=authCurrentUser();if(!$user)throw new \RuntimeException('Perangkat tidak dikenali. Silakan login ulang.');authVerifyPin($user['id'],Yii::$app->request->post('pin',''));return $this->redirect(['/app-portal/index']); }
                if ($action === 'native_biometric_unlock') {
                    $user=authCurrentUser();
                    if(!$user)throw new \RuntimeException('Perangkat tidak dikenali. Silakan login ulang.');
                    if(empty($user['pin_hash']))return $this->redirect(['site/index']);
                    $ua=(string)Yii::$app->request->userAgent;
                    if(!preg_match('/CatatanKeuangan(?:Android|IOS)\/[0-9.]+/i',$ua))throw new \RuntimeException('Buka kunci biometrik hanya tersedia di aplikasi resmi.');
                    $nonce=(string)Yii::$app->request->post('biometric_nonce','');
                    $expected=(string)($_SESSION['native_biometric_unlock_nonce']??'');
                    $issuedAt=(int)($_SESSION['native_biometric_unlock_issued_at']??0);
                    unset($_SESSION['native_biometric_unlock_nonce'],$_SESSION['native_biometric_unlock_issued_at']);
                    if($nonce===''||$expected===''||!hash_equals($expected,$nonce)||$issuedAt<=0||(time()-$issuedAt)>90)throw new \RuntimeException('Permintaan biometrik kedaluwarsa. Silakan buka aplikasi kembali.');
                    authUnlockAfterNativeBiometricGate((int)$user['id']);
                    return $this->redirect(['/app-portal/index']);
                }
                if (in_array($action,['change_password','change_pin'],true)) {
                    $user=authCurrentUser();if(!$user||empty($_SESSION['pin_verified']))throw new \RuntimeException('Buka kunci akun terlebih dahulu.');$uid=(int)$user['id'];
                    if($action==='change_password'){if(Yii::$app->request->post('new_password','')!==Yii::$app->request->post('new_password_confirm',''))throw new \RuntimeException('Konfirmasi password baru tidak sama.');authChangePassword($uid,Yii::$app->request->post('current_password',''),Yii::$app->request->post('new_password',''));$_SESSION['flash_notice']='Password berhasil diubah. Anda tetap login di perangkat ini; perangkat terpercaya lain harus login kembali.';}
                    else{if(Yii::$app->request->post('new_pin','')!==Yii::$app->request->post('new_pin_confirm',''))throw new \RuntimeException('Konfirmasi PIN baru tidak sama.');authChangePin($uid,Yii::$app->request->post('current_pin',''),Yii::$app->request->post('new_pin',''));$_SESSION['flash_notice']='PIN berhasil diubah dan langsung dapat digunakan.';}
                    return $this->redirect(['site/index','email_security'=>1]);
                }
                if (in_array($action,['revoke_device','revoke_other_devices'],true)) {
                    $user=authCurrentUser();if(!$user||empty($_SESSION['pin_verified']))throw new \RuntimeException('Buka kunci akun terlebih dahulu.');$uid=(int)$user['id'];
                    if($action==='revoke_device'){$result=authRevokeDevice($uid,Yii::$app->request->post('device_id',''));if(!empty($result['current'])){authClearLocalSession();return $this->redirect(['site/index','device_logout'=>1]);}$_SESSION['flash_notice']='Perangkat berhasil dikeluarkan dari akun. Perangkat tersebut harus login kembali.';}
                    else{$result=authRevokeOtherDevices($uid);$removed=(int)($result['removed']??0);$_SESSION['flash_notice']=$removed>0?$removed.' perangkat lain berhasil dikeluarkan dari akun.':'Tidak ada perangkat lain yang perlu dikeluarkan.';}
                    return $this->redirect(['site/index','email_security'=>1]);
                }
                if (in_array($action,['save_email','resend_email_verification','verify_email'],true)) {
                    $user=authCurrentUser();if(!$user||empty($_SESSION['pin_verified']))throw new \RuntimeException('Buka kunci akun terlebih dahulu.');$uid=(int)$user['id'];
                    if($action==='save_email'){authUpdateEmail($uid,Yii::$app->request->post('email',''));$r=authRequestEmailVerification($uid);$_SESSION['flash_notice']='Email disimpan. Kode verifikasi telah dikirim ke '.$r['masked'].'.';}
                    elseif($action==='resend_email_verification'){$r=authRequestEmailVerification($uid);$_SESSION['flash_notice']=!empty($r['already_verified'])?'Email Anda sudah terverifikasi.':'Kode verifikasi baru telah dikirim ke '.$r['masked'].'.';}
                    else{authVerifyEmailToken($uid,Yii::$app->request->post('email_token',''));$_SESSION['flash_notice']='Email berhasil diverifikasi. Email ini sekarang dapat digunakan untuk pemulihan password dan PIN.';}
                    return $this->redirect(['site/index','email_security'=>1]);
                }
                if ($action === 'logout') { try { if (authCurrentUser()) setSetting('preferred_app', ''); } catch (\Throwable $ignored) {} unset($_SESSION['force_app_list'], $_SESSION['google_login_nonce'], $_SESSION['pending_sso_authorize']); authLogout(); return $this->redirect(['site/index']); }
            } catch (\Throwable $e) { $error=$e->getMessage(); }
        }

        $forcePinFallback=Yii::$app->request->isGet && Yii::$app->request->get('app_lock')==='1';
        if ($forcePinFallback) {
            $lockUser=authCurrentUser();
            if($lockUser&&!empty($lockUser['pin_hash']))$_SESSION['pin_verified']=false;
            unset($_SESSION['native_biometric_unlock_nonce'],$_SESSION['native_biometric_unlock_issued_at']);
        }
        $user=authCurrentUser();$unlocked=$user&&!empty($_SESSION['pin_verified']);
        if ($user && in_array(strtolower(trim((string)Yii::$app->request->get('app', ''))), ['list', 'chooser'], true)) {
            $_SESSION['force_app_list'] = true;
        }
        $nativeBiometricUnlockNonce='';
        $nativeBiometricAutoUnlockAllowed=false;
        if($user&&!$unlocked&&!empty($user['pin_hash'])&&!$forcePinFallback){
            $nativeBiometricUnlockNonce=bin2hex(random_bytes(24));
            $_SESSION['native_biometric_unlock_nonce']=$nativeBiometricUnlockNonce;
            $_SESSION['native_biometric_unlock_issued_at']=time();
            $nativeBiometricAutoUnlockAllowed=true;
        }
        // Application Hub: setelah akun terbuka, tampilkan galeri atau buka aplikasi terakhir.
        // Parameter app=list/chooser secara eksplisit menahan auto-launch sampai pengguna memilih aplikasi.
        if ($user && $unlocked && !$forcePinFallback) {
            $appMode = strtolower(trim((string)Yii::$app->request->get('app', '')));
            $specialMainView = Yii::$app->request->get('email_security') !== null
                || Yii::$app->request->get('reset') !== null
                || Yii::$app->request->get('device_logout') !== null
                || strtolower(trim((string)Yii::$app->request->get('mode', ''))) === 'forgot';
            if (in_array($appMode, ['list', 'chooser'], true)) {
                unset($_SESSION['pending_sso_authorize']);
                setSetting('preferred_app', '');
                $_SESSION['force_app_list'] = true;
                return $this->renderAppLauncher($user, $error, $notice);
            }
            if ($appMode === '' && !$specialMainView && !empty($_SESSION['pending_sso_authorize']) && is_array($_SESSION['pending_sso_authorize'])) {
                $pendingSso = $_SESSION['pending_sso_authorize'];
                unset($_SESSION['pending_sso_authorize']);
                $pendingIssuedAt = (int)($pendingSso['issued_at'] ?? 0);
                unset($pendingSso['issued_at']);
                if ($pendingIssuedAt > 0 && (time() - $pendingIssuedAt) <= 600) {
                    return $this->redirect(['sso/authorize'] + $pendingSso);
                }
                $_SESSION['force_app_list'] = true;
                return $this->renderAppLauncher($user, 'Permintaan SSO kedaluwarsa. Silakan buka aplikasi dari galeri kembali.', $notice);
            }
            if ($appMode === 'manage') return $this->redirect(['site/app-manager']);
            if ($appMode === 'main') {
                unset($_SESSION['force_app_list'], $_SESSION['pending_sso_authorize']);
                setSetting('preferred_app', 'main');
            } elseif (!$specialMainView) {
                if (!empty($_SESSION['force_app_list'])) return $this->renderAppLauncher($user, $error, $notice);
                $preferredApp = trim((string)setting('preferred_app', ''));
                if ($preferredApp !== '') {
                    return $this->redirect(['site/launch-app', 'slug' => $preferredApp, '_auto' => 1]);
                }
                return $this->renderAppLauncher($user, $error, $notice);
            }
        }

        $googleClientId = trim((string)($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: ''));
        $googleNonce = '';
        if (!$user && $mode === 'login' && $googleClientId !== '') {
            $googleNonce = bin2hex(random_bytes(32));
            $_SESSION['google_login_nonce'] = $googleNonce;
        }
        $emailStatus=$user?authEmailStatus($user):['email'=>'','has_email'=>false,'verified'=>false,'verified_at'=>'','masked'=>''];
        $loginDevices=$user?authListDevices((int)$user['id']):[];
        $emailSecurityRequested=Yii::$app->request->get('email_security')!==null || (Yii::$app->request->isPost && in_array(Yii::$app->request->post('action',''),['save_email','resend_email_verification','verify_email','change_password','change_pin','revoke_device','revoke_other_devices'],true));
        $assetVersion='259-'.max(@filemtime(Yii::getAlias('@webroot/assets/style.css'))?:1,@filemtime(Yii::getAlias('@webroot/assets/app.js'))?:1,@filemtime(Yii::getAlias('@webroot/assets/offline-store.js'))?:1,@filemtime(Yii::getAlias('@webroot/assets/offline-engine.js'))?:1);
        return $this->render('index',compact('error','notice','mode','user','unlocked','emailStatus','loginDevices','emailSecurityRequested','assetVersion','nativeBiometricUnlockNonce','nativeBiometricAutoUnlockAllowed','forcePinFallback','googleClientId','googleNonce'));
    }

    private function renderAppLauncher(array $user, string $error = '', string $notice = '')
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        require_once Yii::getAlias('@app/legacy/db.php');
        $apps = \app\services\AppHubService::all(false);
        $premium = \app\services\AppHubService::isPremium($user);
        $isSuperAdmin = authIsSuperAdmin($user);
        $baseUrl = rtrim((string)(Yii::$app->params['appUrl'] ?: Yii::$app->request->hostInfo), '/');
        $assetVersion = '259-' . max(@filemtime(Yii::getAlias('@webroot/assets/style.css')) ?: 1, @filemtime(Yii::getAlias('@webroot/assets/app.js')) ?: 1);
        return $this->render('app-launcher', compact('user', 'apps', 'premium', 'isSuperAdmin', 'baseUrl', 'assetVersion', 'error', 'notice'));
    }

    public function actionLaunchApp()
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        require_once Yii::getAlias('@app/legacy/db.php');
        $user = authCurrentUser();
        if (!$user) return $this->redirect(['site/index']);
        if (empty($_SESSION['pin_verified'])) return $this->redirect(['site/index']);
        require_once Yii::getAlias('@app/legacy/maintenance_helper.php');
        $maintenance = maintenanceSnapshot();
        if (!empty($maintenance['is_active']) && !maintenanceCanAccess($user, $maintenance)) {
            Yii::$app->response->statusCode = 503;
            Yii::$app->response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            return $this->render('maintenance', ['maintenance' => $maintenance, 'user' => $user]);
        }
        try {
            $url = \app\services\AppHubService::launch((string)Yii::$app->request->get('slug', ''), $user);
            return $this->redirect($url);
        } catch (\Throwable $e) {
            Yii::warning('[Application Hub launch] ' . $e->getMessage(), __METHOD__);
            $_SESSION['flash_error'] = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                ? $e->getMessage()
                : 'Aplikasi tidak dapat dibuka saat ini. Silakan coba lagi atau hubungi bantuan.';
            $_SESSION['force_app_list'] = true;
            return $this->redirect(['site/index', 'app' => 'list']);
        }
    }

    public function actionAppManager()
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        require_once Yii::getAlias('@app/legacy/db.php');
        $user = authCurrentUser();
        if (!$user) return $this->redirect(['site/index']);
        if (empty($_SESSION['pin_verified'])) return $this->redirect(['site/index']);
        if (!authIsSuperAdmin($user)) throw new \yii\web\ForbiddenHttpException('Manajemen Aplikasi hanya tersedia untuk Super Admin.');

        $error = '';
        if (!empty($_SESSION['flash_error'])) { $error = (string)$_SESSION['flash_error']; unset($_SESSION['flash_error']); }
        if (!empty($_SESSION['flash_notice'])) { $notice = (string)$_SESSION['flash_notice']; unset($_SESSION['flash_notice']); } else { $notice = ''; }
        if (Yii::$app->request->isPost) {
            $action = (string)Yii::$app->request->post('hub_action', '');
            try {
                if ($action === 'create') {
                    $result = \app\services\AppHubService::saveExternalApp(null, Yii::$app->request->post());
                    $_SESSION['apphub_generated_credentials'] = ['app_name' => (string)Yii::$app->request->post('name', ''), 'client_id' => $result['client_id'], 'client_secret' => $result['client_secret']];
                    $_SESSION['flash_notice'] = 'Aplikasi berhasil dibuat. Salin Client Secret sekarang; secret hanya ditampilkan satu kali.';
                } elseif ($action === 'update') {
                    \app\services\AppHubService::saveExternalApp((int)Yii::$app->request->post('id', 0), Yii::$app->request->post());
                    $_SESSION['flash_notice'] = 'Perubahan aplikasi berhasil disimpan.';
                } elseif ($action === 'delete') {
                    \app\services\AppHubService::deleteExternalApp((int)Yii::$app->request->post('id', 0));
                    $_SESSION['flash_notice'] = 'Aplikasi berhasil dihapus dari daftar.';
                } elseif ($action === 'regenerate_secret') {
                    $result = \app\services\AppHubService::regenerateSecret((int)Yii::$app->request->post('id', 0));
                    $_SESSION['apphub_generated_credentials'] = ['app_name' => $result['app_name'], 'client_id' => $result['client_id'], 'client_secret' => $result['client_secret']];
                    $_SESSION['flash_notice'] = 'Kredensial SSO baru dibuat. Secret lama langsung tidak berlaku.';
                } else {
                    throw new \InvalidArgumentException('Aksi Manajemen Aplikasi tidak dikenal.');
                }
                return $this->redirect(['site/app-manager']);
            } catch (\Throwable $e) {
                Yii::warning('[Application Hub admin] ' . $e->getMessage(), __METHOD__);
                $_SESSION['flash_error'] = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                    ? $e->getMessage()
                    : 'Perubahan aplikasi tidak dapat disimpan. Periksa konfigurasi database atau coba lagi.';
                return $this->redirect(['site/app-manager']);
            }
        }
        $apps = \app\services\AppHubService::all(true);
        $generatedCredentials = $_SESSION['apphub_generated_credentials'] ?? null;
        unset($_SESSION['apphub_generated_credentials']);
        $baseUrl = rtrim((string)(Yii::$app->params['appUrl'] ?: Yii::$app->request->hostInfo), '/');
        return $this->render('app-manager', compact('user', 'apps', 'error', 'notice', 'generatedCredentials', 'baseUrl'));
    }

    public function actionError()
    {
        $exception = Yii::$app->errorHandler->exception;
        $statusCode = ($exception instanceof \yii\web\HttpException) ? (int)$exception->statusCode : 500;
        if ($statusCode < 400 || $statusCode > 599) $statusCode = 500;

        $request = Yii::$app->request;
        $accept = strtolower((string)$request->headers->get('Accept',''));
        $path = strtolower((string)$request->pathInfo);
        $expectsJson = $request->isAjax
            || str_contains($path, 'legacy-api')
            || str_contains($path, 'api/')
            || (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html'));

        if ($expectsJson) {
            Yii::$app->response->statusCode = $statusCode;
            return $this->asJson([
                'ok' => false,
                'status' => $statusCode,
                'error' => (YII_DEBUG && $exception) ? $exception->getMessage() : $this->publicErrorMessage($statusCode),
            ]);
        }

        $copy = $this->errorPageCopy($statusCode);
        Yii::$app->response->statusCode = $statusCode;
        Yii::$app->response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $this->render('error', array_merge(['statusCode'=>$statusCode], $copy));
    }

    private function publicErrorMessage(int $statusCode): string
    {
        $copy = $this->errorPageCopy($statusCode);
        return (string)($copy['message'] ?? 'Terjadi kesalahan pada server.');
    }

    private function errorPageCopy(int $statusCode): array
    {
        $map = [
            400 => ['title'=>'Permintaan tidak dapat diproses','message'=>'Permintaan yang dikirim tidak valid atau tidak lengkap.','hint'=>'Periksa kembali alamat atau data yang dikirim, lalu coba lagi.','icon'=>'!'],
            401 => ['title'=>'Perlu masuk terlebih dahulu','message'=>'Sesi atau autentikasi diperlukan untuk membuka halaman ini.','hint'=>'Silakan kembali ke aplikasi dan masuk ke akun Anda.','icon'=>'↪'],
            403 => ['title'=>'Akses dibatasi','message'=>'Anda tidak memiliki izin untuk mengakses halaman atau sumber daya ini.','hint'=>'Jika seharusnya Anda memiliki akses, kembali ke aplikasi lalu coba masuk kembali.','icon'=>'🔒'],
            404 => ['title'=>'Halaman tidak ditemukan','message'=>'Halaman yang Anda cari tidak tersedia, sudah dipindahkan, atau alamatnya tidak tepat.','hint'=>'Periksa alamat halaman atau kembali ke aplikasi.','icon'=>'⌕'],
            408 => ['title'=>'Permintaan terlalu lama','message'=>'Server menghentikan permintaan karena membutuhkan waktu terlalu lama.','hint'=>'Periksa koneksi internet Anda kemudian coba lagi.','icon'=>'◷'],
            429 => ['title'=>'Terlalu banyak permintaan','message'=>'Terlalu banyak permintaan diterima dalam waktu singkat.','hint'=>'Tunggu sebentar sebelum mencoba kembali.','icon'=>'⏳'],
            500 => ['title'=>'Terjadi gangguan di server','message'=>'Aplikasi mengalami kesalahan saat memproses permintaan Anda.','hint'=>'Data Anda tidak otomatis berubah karena halaman ini. Coba lagi beberapa saat lagi.','icon'=>'⚠'],
            502 => ['title'=>'Layanan sementara tidak terhubung','message'=>'Server utama belum dapat menerima respons dari layanan di belakangnya.','hint'=>'Coba muat ulang setelah beberapa saat.','icon'=>'↔'],
            503 => ['title'=>'Layanan sementara tidak tersedia','message'=>'Aplikasi sedang sibuk atau dalam proses pemeliharaan.','hint'=>'Silakan coba kembali beberapa saat lagi.','icon'=>'⚙'],
            504 => ['title'=>'Respons server terlalu lama','message'=>'Layanan di belakang server membutuhkan waktu terlalu lama untuk merespons.','hint'=>'Periksa koneksi dan coba kembali beberapa saat lagi.','icon'=>'◴'],
        ];
        return $map[$statusCode] ?? ['title'=>'Permintaan tidak dapat diselesaikan','message'=>'Aplikasi tidak dapat menyelesaikan permintaan ini.','hint'=>'Silakan kembali atau coba lagi beberapa saat lagi.','icon'=>'⚠'];
    }
}
