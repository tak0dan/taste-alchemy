<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Request;
use App\Core\Session;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;

final class AuthService
{
    private static ?array $user = null;
    private static bool $resolved = false;
    private static ?array $venueProfile = null;
    private static bool $venueResolved = false;

    public static function boot(Request $request): void
    {
        $token = $request->bearerToken();
        if ($token !== null) {
            $userId = self::verifyToken($token);
            if ($userId !== null) {
                $user = (new UserRepository())->find($userId);
                if ($user !== null && $user['status'] === 'activo') {
                    self::setUser($user);
                }
            }
            return;
        }

        $id = Session::get('auth_user_id');
        if ($id) {
            $user = (new UserRepository())->find((int) $id);
            if ($user !== null && $user['status'] === 'activo') {
                self::setUser($user);
            } else {
                Session::remove('auth_user_id');
            }
        }
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::$user === null ? null : (int) self::$user['id'];
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function guest(): bool
    {
        return self::$user === null;
    }

    /** @param array<string, mixed> $user */
    public static function setUser(array $user): void
    {
        self::$user = $user;
        self::$resolved = true;
        self::$venueProfile = null;
        self::$venueResolved = false;
    }

    public static function forget(): void
    {
        self::$user = null;
        self::$resolved = false;
        self::$venueProfile = null;
        self::$venueResolved = false;
    }

    public static function attempt(string $email, string $password): ?array
    {
        $user = (new UserRepository())->findByEmail($email);
        if ($user === null || $user['status'] !== 'activo') {
            return null;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }
        (new UserRepository())->setLastLogin((int) $user['id']);
        unset($user['password_hash']);
        return $user;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('auth_user_id', (int) $user['id']);
        self::setUser($user);
    }

    public static function logout(): void
    {
        Session::remove('auth_user_id');
        Session::destroy();
        self::forget();
    }

    public static function hasRole(string $role): bool
    {
        if (!self::check()) {
            return false;
        }
        $roles = self::$user['roles'] ?? [];
        return in_array('admin', $roles, true) || in_array($role, $roles, true);
    }

    /** @param array<int, string> $roles */
    public static function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if (self::hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    public static function isVerifiedVenue(): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::$venueResolved) {
            return self::$venueProfile !== null && self::$venueProfile['license_status'] === 'verificada';
        }
        self::$venueProfile = (new ProfileRepository())->venue((int) self::$user['id']);
        self::$venueResolved = true;
        return self::$venueProfile !== null && self::$venueProfile['license_status'] === 'verificada';
    }

    public static function venueProfile(): ?array
    {
        self::isVerifiedVenue();
        return self::$venueProfile;
    }

    public static function homeFor(?array $user = null): string
    {
        $user ??= self::$user;
        if ($user === null) {
            return '/';
        }
        $roles = $user['roles'] ?? [];
        if (in_array('admin', $roles, true)) {
            return '/admin';
        }
        if (in_array('moderador', $roles, true)) {
            return '/moderation';
        }
        if (in_array('local', $roles, true)) {
            return '/bar';
        }
        if (in_array('artesano', $roles, true)) {
            return '/workshop';
        }
        if (in_array('comisario', $roles, true)) {
            return '/curator';
        }
        return '/';
    }

    public static function issueToken(int $userId, ?int $ttl = null): string
    {
        $ttl ??= Config::int('TOKEN_TTL', 86400);
        $payload = self::base64UrlEncode(json_encode([
            'uid' => $userId,
            'iat' => time(),
            'exp' => time() + $ttl,
        ], JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $payload, (string) Config::get('APP_SECRET', 'taste-alchemy'));
        return $payload . '.' . $signature;
    }

    public static function verifyToken(string $token): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$payload, $signature] = $parts;
        $expected = hash_hmac('sha256', $payload, (string) Config::get('APP_SECRET', 'taste-alchemy'));
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        $decoded = json_decode((string) self::base64UrlDecode($payload), true);
        if (!is_array($decoded) || empty($decoded['uid']) || empty($decoded['exp'])) {
            return null;
        }
        if ((int) $decoded['exp'] < time()) {
            return null;
        }
        return (int) $decoded['uid'];
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string|false
    {
        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
