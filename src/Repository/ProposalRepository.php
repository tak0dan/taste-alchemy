<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class ProposalRepository
{
    private const BASE_SELECT = 'SELECT p.*, rq.title AS request_title, rq.territory AS request_territory,
            rq.target_date, rq.units_wanted, rq.abv_min, rq.abv_max, rq.status AS request_status,
            rq.exclusivity_requested, vp.venue_name, vp.slug AS venue_slug,
            l.code AS lot_code, l.coherence_score, l.status AS lot_status, l.unit_ml,
            (l.units_total - l.units_reserved - l.units_sold) AS units_available,
            l.initial_abv, l.declared_abv, l.started_at, l.territory AS lot_territory,
            r.title AS recipe_title, r.slug AS recipe_slug, rv.version_no,
            u.display_name AS artisan_name, ap.studio_name, ap.slug AS artisan_slug
     FROM proposals p
     JOIN requests rq ON rq.id = p.request_id
     JOIN venue_profiles vp ON vp.user_id = rq.venue_id
     JOIN lots l ON l.id = p.lot_id
     JOIN recipe_versions rv ON rv.id = l.recipe_version_id
     JOIN recipes r ON r.id = rv.recipe_id
     JOIN users u ON u.id = p.artisan_id
     LEFT JOIN artisan_profiles ap ON ap.user_id = p.artisan_id';

    public function find(int $id): ?array
    {
        return Database::first(self::BASE_SELECT . ' WHERE p.id = ?', [$id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function forRequest(int $requestId): array
    {
        return Database::select(
            self::BASE_SELECT . ' WHERE p.request_id = ? ORDER BY l.coherence_score DESC, p.price_per_unit ASC',
            [$requestId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function forArtisan(int $artisanId): array
    {
        return Database::select(
            self::BASE_SELECT . ' WHERE p.artisan_id = ? ORDER BY p.created_at DESC',
            [$artisanId]
        );
    }

    public function exists(int $requestId, int $lotId, int $artisanId): bool
    {
        return (bool) Database::value(
            'SELECT COUNT(*) FROM proposals WHERE request_id = ? AND lot_id = ? AND artisan_id = ? AND status IN ("pendiente","aceptada")',
            [$requestId, $lotId, $artisanId]
        );
    }

    public function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO proposals
             (request_id, lot_id, artisan_id, units, price_per_unit, mode, exclusivity, exclusivity_fee, message, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pendiente")',
            [
                (int) $data['request_id'],
                (int) $data['lot_id'],
                (int) $data['artisan_id'],
                (int) $data['units'],
                (float) $data['price_per_unit'],
                (string) ($data['mode'] ?? 'ambas'),
                !empty($data['exclusivity']) ? 1 : 0,
                (float) ($data['exclusivity_fee'] ?? 0),
                $data['message'] ?? null,
            ]
        );
    }

    public function withdraw(int $id, int $artisanId): int
    {
        return Database::execute(
            'UPDATE proposals SET status = "retirada" WHERE id = ? AND artisan_id = ? AND status = "pendiente"',
            [$id, $artisanId]
        );
    }

    public function countPendingForArtisan(int $artisanId): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM proposals WHERE artisan_id = ? AND status = "pendiente"',
            [$artisanId]
        );
    }
}
