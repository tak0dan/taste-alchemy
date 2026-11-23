<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class ProfileRepository
{
    public function artisan(int $userId): ?array
    {
        return Database::first(
            'SELECT ap.*, u.display_name, u.email, u.nif, u.status AS user_status
             FROM artisan_profiles ap JOIN users u ON u.id = ap.user_id
             WHERE ap.user_id = ?',
            [$userId]
        );
    }

    public function artisanBySlug(string $slug): ?array
    {
        return Database::first(
            'SELECT ap.*, u.display_name, u.email, u.status AS user_status,
                    (SELECT COUNT(*) FROM recipes r WHERE r.author_id = ap.user_id AND r.visibility = "publica") AS recipe_count
             FROM artisan_profiles ap JOIN users u ON u.id = ap.user_id
             WHERE ap.slug = ?',
            [$slug]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function allArtisans(): array
    {
        return Database::select(
            'SELECT * FROM v_artisan_scorecard ORDER BY avg_coherence DESC, public_recipes DESC'
        );
    }

    public function venue(int $userId): ?array
    {
        return Database::first(
            'SELECT vp.*, u.display_name, u.email, u.nif, u.status AS user_status
             FROM venue_profiles vp JOIN users u ON u.id = vp.user_id
             WHERE vp.user_id = ?',
            [$userId]
        );
    }

    public function venueBySlug(string $slug): ?array
    {
        return Database::first(
            'SELECT * FROM v_venue_trust WHERE slug = ?',
            [$slug]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function allVenues(): array
    {
        return Database::select('SELECT * FROM v_venue_trust ORDER BY verified_at DESC, venue_name');
    }

    public function createArtisan(int $userId, array $data): void
    {
        Database::execute(
            'INSERT INTO artisan_profiles (user_id, studio_name, slug, territory, bio, founded_year, website)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                (string) $data['studio_name'],
                (string) $data['slug'],
                (string) $data['territory'],
                $data['bio'] ?? null,
                $data['founded_year'] ?: null,
                $data['website'] ?? null,
            ]
        );
    }

    public function createVenue(int $userId, array $data): void
    {
        Database::execute(
            'INSERT INTO venue_profiles (user_id, venue_name, slug, license_number, license_type, territory, address, license_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                (string) $data['venue_name'],
                (string) $data['slug'],
                $data['license_number'] ?? null,
                $data['license_type'] ?? 'Licencia de restauración',
                (string) $data['territory'],
                $data['address'] ?? null,
                $data['license_status'] ?? 'pendiente',
            ]
        );
    }

    public function updateVenue(int $userId, array $data): void
    {
        Database::execute(
            'UPDATE venue_profiles
             SET venue_name = ?, territory = ?, address = ?, license_number = ?, license_type = ?
             WHERE user_id = ?',
            [
                (string) $data['venue_name'],
                (string) $data['territory'],
                $data['address'] ?? null,
                $data['license_number'] ?? null,
                $data['license_type'] ?? null,
                $userId,
            ]
        );
    }

    public function updateArtisan(int $userId, array $data): void
    {
        Database::execute(
            'UPDATE artisan_profiles
             SET studio_name = ?, territory = ?, bio = ?, founded_year = ?, website = ?
             WHERE user_id = ?',
            [
                (string) $data['studio_name'],
                (string) $data['territory'],
                $data['bio'] ?? null,
                $data['founded_year'] ?: null,
                $data['website'] ?? null,
                $userId,
            ]
        );
    }

    public function anonymizeArtisan(int $userId): void
    {
        Database::execute(
            "UPDATE artisan_profiles
             SET studio_name = 'Taller eliminado', bio = NULL, website = NULL, avatar_path = NULL
             WHERE user_id = ?",
            [$userId]
        );
    }

    public function anonymizeVenue(int $userId): void
    {
        Database::execute(
            "UPDATE venue_profiles
             SET venue_name = 'Local eliminado', address = NULL, license_number = NULL,
                 license_status = 'suspendida', document_ref = NULL
             WHERE user_id = ?",
            [$userId]
        );
    }

    public function setLicenseStatus(int $userId, string $status, ?int $verifierId): void
    {
        Database::execute(
            'UPDATE venue_profiles
             SET license_status = ?, verified_at = IF(? = "verificada", NOW(), verified_at), verified_by = ?
             WHERE user_id = ?',
            [$status, $status, $verifierId, $userId]
        );
    }

    public function slugExists(string $slug, string $table = 'artisan_profiles'): bool
    {
        $allowed = ['artisan_profiles', 'venue_profiles'];
        if (!in_array($table, $allowed, true)) {
            $table = 'artisan_profiles';
        }
        return (bool) Database::value("SELECT COUNT(*) FROM {$table} WHERE slug = ?", [$slug]);
    }

    public function publicArtisanPortfolio(int $userId): array
    {
        return [
            'profile' => $this->artisan($userId),
            'scorecard' => Database::first('SELECT * FROM v_artisan_scorecard WHERE artisan_id = ?', [$userId]),
            'recipes' => Database::select(
                "SELECT * FROM v_recipe_catalog WHERE author_id = ? ORDER BY updated_at DESC",
                [$userId]
            ),
            'lots' => Database::select(
                "SELECT * FROM v_lot_public WHERE artisan_id = ? AND status NOT IN ('descartado','bloqueado') ORDER BY started_at DESC",
                [$userId]
            ),
        ];
    }
}
