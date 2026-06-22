<?php
declare(strict_types=1);

namespace Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;

final class Auth
{
    private const ALG = 'HS256';

    public static function issueAccessToken(array $user): string
    {
        $now = time();
        return JWT::encode([
            'iss'   => APP_URL,
            'iat'   => $now,
            'exp'   => $now + JWT_EXPIRY,
            'sub'   => (string) $user['id'],
            'role'  => $user['role'],
            'email' => $user['email'],
            'name'  => trim($user['first_name'] . ' ' . $user['last_name']),
        ], JWT_SECRET, self::ALG);
    }

    public static function issueRefreshToken(array $user): string
    {
        $now = time();
        return JWT::encode([
            'iss' => APP_URL,
            'iat' => $now,
            'exp' => $now + JWT_REFRESH_EXPIRY,
            'sub' => (string) $user['id'],
            'typ' => 'refresh',
        ], JWT_SECRET, self::ALG);
    }

    public static function verifyAccessToken(string $token): ?stdClass
    {
        try {
            $d = JWT::decode($token, new Key(JWT_SECRET, self::ALG));
            return ($d->typ ?? 'access') === 'refresh' ? null : $d;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function verifyRefreshToken(string $token): ?stdClass
    {
        try {
            $d = JWT::decode($token, new Key(JWT_SECRET, self::ALG));
            return ($d->typ ?? '') === 'refresh' ? $d : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /** Extract Bearer token from all possible Apache/XAMPP header locations. */
    public static function bearerToken(): ?string
    {
        $candidates = [
            $_SERVER['HTTP_AUTHORIZATION']          ?? '',
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        ];
        if (function_exists('apache_request_headers')) {
            $h = apache_request_headers();
            $candidates[] = $h['Authorization']  ?? '';
            $candidates[] = $h['authorization']  ?? '';
        }
        foreach ($candidates as $v) {
            if ($v && preg_match('/Bearer\s+(\S+)/i', $v, $m)) return $m[1];
        }
        return null;
    }
}
