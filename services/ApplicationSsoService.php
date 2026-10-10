<?php
namespace app\services;

use Yii;
use app\repositories\ApplicationHubRepository;

/** Creates a short-lived signed assertion for an app that explicitly supports the CK SSO protocol. */
final class ApplicationSsoService
{
    public static function createLaunchToken(array $app, array $user): string
    {
        $secret = ApplicationHubRepository::getSsoSecret((string)($app['id'] ?? ''));
        if ($secret === '' || strlen($secret) < 24) throw new \RuntimeException('Kunci SSO aplikasi tujuan belum tersedia.');
        $issuer = rtrim((string)(Yii::$app->params['appUrl'] ?: Yii::$app->request->hostInfo), '/');
        $now = time();
        $claims = [
            'iss' => $issuer,
            'aud' => (string)$app['id'],
            'sub' => (string)(int)($user['id'] ?? 0),
            'username' => (string)($user['username'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'name' => (string)($user['google_name'] ?? $user['username'] ?? ''),
            'premium' => function_exists('authHasPremiumAccess') ? authHasPremiumAccess($user) : false,
            'plan_expires_at' => (string)($user['plan_expires_at'] ?? ''),
            'iat' => $now,
            'nbf' => $now - 2,
            'exp' => $now + 60,
            'jti' => bin2hex(random_bytes(16)),
        ];
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $encodedHeader = self::base64url(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedClaims = self::base64url(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $signingInput = $encodedHeader . '.' . $encodedClaims;
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        return $signingInput . '.' . self::base64url($signature);
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
