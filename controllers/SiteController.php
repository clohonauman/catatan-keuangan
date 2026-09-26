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
        $maintenanceAuthPost = in_array($incomingAction, ['login','logout','register','request_recovery','reset_with_email_token'], true);
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
        $mode=(string)Yii::$app->request->get('mode','login');

        if (Yii::$app->request->isPost) {
            $action=(string)Yii::$app->request->post('action','');
            try {
                if ($action === 'register') {
                    $newUser=authRegister(Yii::$app->request->post('username',''),Yii::$app->request->post('email',''),Yii::$app->request->post('password',''));
                    try {$verification=authRequestEmailVerification((int)$newUser['id']);$_SESSION['flash_notice']='Akun berhasil dibuat. Kode verifikasi telah dikirim ke '.$verification['masked'].'.';}
                    catch(\Throwable $e){$_SESSION['flash_notice']='Akun berhasil dibuat dan email sudah tersimpan. Kode verifikasi belum dapat dikirim: '.$e->getMessage();}
                    return $this->redirect(['site/index']);
                }
                if ($action === 'login') { authLogin(Yii::$app->request->post('username',''),Yii::$app->request->post('password','')); return $this->redirect(['site/index']); }
                if ($action === 'request_recovery') { $identifier=trim((string)Yii::$app->request->post('identifier',''));$result=authRequestRecoveryToken($identifier);$_SESSION['recovery_identifier']=$identifier;$_SESSION['flash_notice']='Token pemulihan telah dikirim ke '.$result['masked'].'. Token berlaku 15 menit.';return $this->redirect(['site/index','mode'=>'forgot','sent'=>1]); }
                if ($action === 'reset_with_email_token') {
                    if (Yii::$app->request->post('password','') !== Yii::$app->request->post('password_confirm','')) throw new \RuntimeException('Konfirmasi password baru tidak sama.');
                    if (Yii::$app->request->post('pin','') !== Yii::$app->request->post('pin_confirm','')) throw new \RuntimeException('Konfirmasi PIN baru tidak sama.');
                    authResetWithEmailToken(Yii::$app->request->post('identifier',$_SESSION['recovery_identifier']??''),Yii::$app->request->post('token',''),Yii::$app->request->post('password',''),Yii::$app->request->post('pin',''));
                    unset($_SESSION['recovery_identifier']); return $this->redirect(['site/index','reset'=>'success']);
                }
                if ($action === 'set_pin') { $user=authCurrentUser();if(!$user)throw new \RuntimeException('Sesi login tidak ditemukan.');if(Yii::$app->request->post('pin','')!==Yii::$app->request->post('pin_confirm',''))throw new \RuntimeException('Konfirmasi PIN tidak sama.');authSetPin($user['id'],Yii::$app->request->post('pin',''));return $this->redirect(['site/index']); }
                if ($action === 'unlock') { $user=authCurrentUser();if(!$user)throw new \RuntimeException('Perangkat tidak dikenali. Silakan login ulang.');authVerifyPin($user['id'],Yii::$app->request->post('pin',''));return $this->redirect(['site/index']); }
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
                    return $this->redirect(['site/index']);
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
                if ($action === 'logout') { authLogout(); return $this->redirect(['site/index']); }
            } catch (\Throwable $e) { $error=$e->getMessage(); }
        }

        $forcePinFallback=Yii::$app->request->isGet && Yii::$app->request->get('app_lock')==='1';
        if ($forcePinFallback) {
            $lockUser=authCurrentUser();
            if($lockUser&&!empty($lockUser['pin_hash']))$_SESSION['pin_verified']=false;
            unset($_SESSION['native_biometric_unlock_nonce'],$_SESSION['native_biometric_unlock_issued_at']);
        }
        $user=authCurrentUser();$unlocked=$user&&!empty($_SESSION['pin_verified']);
        $nativeBiometricUnlockNonce='';
        $nativeBiometricAutoUnlockAllowed=false;
        if($user&&!$unlocked&&!empty($user['pin_hash'])&&!$forcePinFallback){
            $nativeBiometricUnlockNonce=bin2hex(random_bytes(24));
            $_SESSION['native_biometric_unlock_nonce']=$nativeBiometricUnlockNonce;
            $_SESSION['native_biometric_unlock_issued_at']=time();
            $nativeBiometricAutoUnlockAllowed=true;
        }
        $emailStatus=$user?authEmailStatus($user):['email'=>'','has_email'=>false,'verified'=>false,'verified_at'=>'','masked'=>''];
        $loginDevices=$user?authListDevices((int)$user['id']):[];
        $emailSecurityRequested=Yii::$app->request->get('email_security')!==null || (Yii::$app->request->isPost && in_array(Yii::$app->request->post('action',''),['save_email','resend_email_verification','verify_email','change_password','change_pin','revoke_device','revoke_other_devices'],true));
        $assetVersion=max(@filemtime(Yii::getAlias('@webroot/assets/style.css'))?:1,@filemtime(Yii::getAlias('@webroot/assets/app.js'))?:1,@filemtime(Yii::getAlias('@webroot/assets/offline-store.js'))?:1);
        return $this->render('index',compact('error','notice','mode','user','unlocked','emailStatus','loginDevices','emailSecurityRequested','assetVersion','nativeBiometricUnlockNonce','nativeBiometricAutoUnlockAllowed','forcePinFallback'));
    }

    public function actionError(){ $e=Yii::$app->errorHandler->exception; return $this->asJson(['ok'=>false,'error'=>YII_DEBUG?$e->getMessage():'Terjadi kesalahan pada server.']); }
}
