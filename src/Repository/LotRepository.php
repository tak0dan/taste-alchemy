<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class LotRepository
{
    public function find(int $id): ?array
    {
        return Database::first('SELECT * FROM v_lot_public WHERE id = ?', [$id]);
    }

    public function findRaw(int $id): ?array
    {
        return Database::first('SELECT * FROM lots WHERE id = ?', [$id]);
    }

    public function findByCode(string $code): ?array
    {
        return Database::first('SELECT * FROM v_lot_public WHERE code = ?', [$code]);
    }

    /** @return array<int, array<string, mixed>> */
    public function byArtisan(int $artisanId): array
    {
        return Database::select(
            'SELECT * FROM v_lot_public WHERE artisan_id = ? ORDER BY started_at DESC',
            [$artisanId]
        );
    }

    /**
     * @param array{q?:string, territory?:string, min_coherence?:int, status?:string, sort?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function publicList(array $filters = [], int $limit = 24, int $offset = 0): array
    {
        $where = ["status NOT IN ('descartado','bloqueado')"];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(code LIKE ? OR recipe_title LIKE ? OR studio_name LIKE ? OR barrel_ref LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term, $term, $term);
        }
        if (!empty($filters['territory'])) {
            $where[] = 'territory LIKE ?';
            $params[] = '%' . $filters['territory'] . '%';
        }
        if (!empty($filters['min_coherence'])) {
            $where[] = 'coherence_score >= ?';
            $params[] = (int) $filters['min_coherence'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        $order = match ($filters['sort'] ?? 'coherence') {
            'recent' => 'started_at DESC',
            'available' => 'units_available DESC, coherence_score DESC',
            'age' => 'started_at ASC',
            default => 'coherence_score DESC, started_at ASC',
        };

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        return Database::select(
            'SELECT * FROM v_lot_public WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $order . ' LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
    }

    public function countPublic(array $filters = []): int
    {
        $where = ["status NOT IN ('descartado','bloqueado')"];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(code LIKE ? OR recipe_title LIKE ? OR studio_name LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term, $term);
        }
        if (!empty($filters['territory'])) {
            $where[] = 'territory LIKE ?';
            $params[] = '%' . $filters['territory'] . '%';
        }
        if (!empty($filters['min_coherence'])) {
            $where[] = 'coherence_score >= ?';
            $params[] = (int) $filters['min_coherence'];
        }
        return (int) Database::value('SELECT COUNT(*) FROM v_lot_public WHERE ' . implode(' AND ', $where), $params);
    }

    public function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO lots
             (code, artisan_id, recipe_version_id, barrel_ref, territory, initial_volume_l, current_volume_l,
              initial_abv, declared_abv, started_at, status, units_total, unit_ml, price_per_unit,
              is_last_of_barrel, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (string) $data['code'],
                (int) $data['artisan_id'],
                (int) $data['recipe_version_id'],
                $data['barrel_ref'] ?? null,
                (string) $data['territory'],
                (float) $data['initial_volume_l'],
                (float) ($data['current_volume_l'] ?? $data['initial_volume_l']),
                (float) $data['initial_abv'],
                (float) ($data['declared_abv'] ?? $data['initial_abv']),
                (string) $data['started_at'],
                $data['status'] ?? 'en_maceracion',
                (int) ($data['units_total'] ?? 0),
                (int) ($data['unit_ml'] ?? 700),
                isset($data['price_per_unit']) ? (float) $data['price_per_unit'] : null,
                !empty($data['is_last_of_barrel']) ? 1 : 0,
                $data['notes'] ?? null,
            ]
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        Database::execute(
            'UPDATE lots SET status = ?, bottled_at = IF(? = "embotellado", COALESCE(bottled_at, CURDATE()), bottled_at) WHERE id = ?',
            [$status, $status, $id]
        );
    }

    public function updateMeta(int $id, array $data): void
    {
        Database::execute(
            'UPDATE lots SET barrel_ref = ?, price_per_unit = ?, notes = ?, is_last_of_barrel = ? WHERE id = ?',
            [
                $data['barrel_ref'] ?? null,
                isset($data['price_per_unit']) ? (float) $data['price_per_unit'] : null,
                $data['notes'] ?? null,
                !empty($data['is_last_of_barrel']) ? 1 : 0,
                $id,
            ]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function readings(int $lotId): array
    {
        return Database::select(
            'SELECT lr.*, u.display_name AS recorded_by_name
             FROM lot_readings lr LEFT JOIN users u ON u.id = lr.recorded_by
             WHERE lr.lot_id = ? ORDER BY lr.taken_at ASC, lr.id ASC',
            [$lotId]
        );
    }

    public function latestVerdict(int $lotId): ?array
    {
        return Database::first(
            'SELECT * FROM lot_verdicts WHERE lot_id = ? ORDER BY computed_at DESC, id DESC LIMIT 1',
            [$lotId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function verdictHistory(int $lotId): array
    {
        return Database::select(
            'SELECT id, score, evaporation_score, abv_score, cadence_score, declaration_score, computed_at
             FROM lot_verdicts WHERE lot_id = ? ORDER BY computed_at DESC LIMIT 12',
            [$lotId]
        );
    }

    public function listingForLot(int $lotId): ?array
    {
        return Database::first(
            'SELECT * FROM listings WHERE lot_id = ? ORDER BY created_at DESC LIMIT 1',
            [$lotId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listings(int $artisanId): array
    {
        return Database::select(
            'SELECT li.*, l.code AS lot_code, l.coherence_score
             FROM listings li JOIN lots l ON l.id = li.lot_id
             WHERE li.artisan_id = ? ORDER BY li.created_at DESC',
            [$artisanId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function publicListings(int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        return Database::select(
            "SELECT li.*, l.code AS lot_code, l.coherence_score, l.units_total, l.units_reserved, l.units_sold,
                    l.unit_ml, l.territory, l.started_at, r.title AS recipe_title, r.slug AS recipe_slug,
                    ap.studio_name, ap.slug AS artisan_slug, u.display_name AS artisan_name
             FROM listings li
             JOIN lots l ON l.id = li.lot_id
             JOIN recipe_versions rv ON rv.id = l.recipe_version_id
             JOIN recipes r ON r.id = rv.recipe_id
             JOIN users u ON u.id = li.artisan_id
             LEFT JOIN artisan_profiles ap ON ap.user_id = li.artisan_id
             WHERE li.status = 'publicado' AND l.status NOT IN ('descartado','bloqueado')
             ORDER BY l.coherence_score DESC, li.published_at DESC
             LIMIT " . $limit
        );
    }

    public function createListing(array $data): int
    {
        return Database::insert(
            'INSERT INTO listings (lot_id, artisan_id, title, description, units_available, price_per_unit, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['lot_id'],
                (int) $data['artisan_id'],
                (string) $data['title'],
                $data['description'] ?? null,
                (int) $data['units_available'],
                (float) $data['price_per_unit'],
                $data['status'] ?? 'borrador',
            ]
        );
    }

    public function updateListing(int $listingId, array $data): void
    {
        Database::execute(
            'UPDATE listings SET title = ?, description = ?, units_available = ?, price_per_unit = ?, status = ? WHERE id = ?',
            [
                (string) $data['title'],
                $data['description'] ?? null,
                (int) $data['units_available'],
                (float) $data['price_per_unit'],
                (string) $data['status'],
                $listingId,
            ]
        );
    }

    public function unitsAvailable(int $lotId): int
    {
        return (int) Database::value(
            'SELECT units_total - units_reserved - units_sold FROM lots WHERE id = ?',
            [$lotId]
        );
    }

    public function stats(): array
    {
        return [
            'lots' => (int) Database::value('SELECT COUNT(*) FROM lots'),
            'avg_coherence' => (float) Database::value('SELECT COALESCE(AVG(coherence_score), 0) FROM lots WHERE coherence_score > 0'),
            'high_trust' => (int) Database::value('SELECT COUNT(*) FROM lots WHERE coherence_score >= 85'),
            'listings' => (int) Database::value("SELECT COUNT(*) FROM listings WHERE status = 'publicado'"),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 4): array
    {
        $limit = max(1, min(20, $limit));
        return Database::select(
            'SELECT * FROM v_lot_public WHERE status NOT IN ("descartado","bloqueado")
             ORDER BY coherence_score DESC, started_at ASC LIMIT ' . $limit
        );
    }
}
