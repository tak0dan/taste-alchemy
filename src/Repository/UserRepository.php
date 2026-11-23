<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class UserRepository
{
    public function find(int $id): ?array
    {
        $user = Database::first(
            'SELECT id, email, password_hash, display_name, birth_date, nif, phone, status, last_login_at, created_at
             FROM users WHERE id = ?',
            [$id]
        );
        if ($user === null) {
            return null;
        }
        $user['roles'] = $this->roles($id);
        return $user;
    }

    public function findByEmail(string $email): ?array
    {
        $user = Database::first(
            'SELECT id, email, password_hash, display_name, birth_date, nif, phone, status, last_login_at, created_at
             FROM users WHERE email = ?',
            [strtolower(trim($email))]
        );
        if ($user === null) {
            return null;
        }
        $user['roles'] = $this->roles((int) $user['id']);
        return $user;
    }

    public function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO users (email, password_hash, display_name, birth_date, nif, phone)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                strtolower(trim((string) $data['email'])),
                (string) $data['password_hash'],
                (string) $data['display_name'],
                $data['birth_date'] ?: null,
                $data['nif'] ?: null,
                $data['phone'] ?: null,
            ]
        );
    }

    public function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE users SET display_name = ?, birth_date = ?, nif = ?, phone = ? WHERE id = ?',
            [
                (string) $data['display_name'],
                $data['birth_date'] ?: null,
                $data['nif'] ?: null,
                $data['phone'] ?: null,
                $id,
            ]
        );
    }

    public function setLastLogin(int $id): void
    {
        Database::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    /** @return array<int, string> */
    public function roles(int $id): array
    {
        $rows = Database::select(
            'SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.code',
            [$id]
        );
        return array_map(static fn(array $row): string => (string) $row['code'], $rows);
    }

    public function addRole(int $userId, string $code, ?int $grantedBy = null): void
    {
        Database::execute(
            'INSERT IGNORE INTO user_roles (user_id, role_id, granted_by)
             SELECT ?, id, ? FROM roles WHERE code = ?',
            [$userId, $grantedBy, $code]
        );
    }

    public function removeRole(int $userId, string $code): void
    {
        Database::execute(
            'DELETE ur FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.code = ?',
            [$userId, $code]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function allWithRoles(): array
    {
        $users = Database::select(
            'SELECT id, email, display_name, nif, status, created_at, last_login_at FROM users ORDER BY created_at DESC'
        );
        foreach ($users as &$user) {
            $user['roles'] = $this->roles((int) $user['id']);
        }
        return $users;
    }

    public function countByRole(string $code): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.code = ?',
            [$code]
        );
    }

    public function countAll(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM users WHERE status = 'activo'");
    }

    public function anonymize(int $id): void
    {
        Database::execute(
            "UPDATE users
             SET email = CONCAT('eliminado+', id, '@tastealchemy.invalid'),
                 display_name = 'Cuenta eliminada',
                 password_hash = ?,
                 nif = NULL,
                 birth_date = NULL,
                 phone = NULL,
                 status = 'eliminado'
             WHERE id = ?",
            [password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), $id]
        );
    }

    public function export(int $id): array
    {
        $user = $this->find($id);
        if ($user === null) {
            return [];
        }
        unset($user['password_hash']);

        return [
            'exported_at' => date('c'),
            'user' => $user,
            'artisan_profile' => Database::first('SELECT * FROM artisan_profiles WHERE user_id = ?', [$id]),
            'venue_profile' => Database::first('SELECT * FROM venue_profiles WHERE user_id = ?', [$id]),
            'recipes' => Database::select(
                'SELECT r.id, r.slug, r.title, r.style, r.created_at, r.visibility
                 FROM recipes r WHERE r.author_id = ?',
                [$id]
            ),
            'lots' => Database::select(
                'SELECT id, code, status, started_at, coherence_score FROM lots WHERE artisan_id = ?',
                [$id]
            ),
            'requests' => Database::select(
                'SELECT id, title, territory, status, created_at FROM requests WHERE venue_id = ?',
                [$id]
            ),
            'commissions' => Database::select(
                'SELECT id, units, total_amount, mode, status, created_at, completed_at
                 FROM commissions WHERE venue_id = ? OR artisan_id = ?',
                [$id, $id]
            ),
            'reviews' => Database::select(
                'SELECT id, lot_id, kind, score_total, body, created_at FROM reviews WHERE author_id = ?',
                [$id]
            ),
            'notifications' => Database::select(
                'SELECT type, title, body, created_at FROM notifications WHERE user_id = ?',
                [$id]
            ),
        ];
    }
}
