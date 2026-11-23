<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class CommissionRepository
{
    private const BASE_SELECT = 'SELECT c.*,
            rq.title AS request_title, rq.territory, rq.target_date,
            vp.venue_name, vp.slug AS venue_slug, vp.license_status,
            uv.display_name AS venue_contact, uv.nif AS venue_nif,
            ua.display_name AS artisan_name, ua.nif AS artisan_nif,
            ap.studio_name, ap.slug AS artisan_slug,
            l.code AS lot_code, l.status AS lot_status, l.started_at, l.coherence_score,
            l.unit_ml, l.initial_abv, l.declared_abv,
            r.title AS recipe_title, r.slug AS recipe_slug, rv.version_no,
            cs.id AS consignment_id, cs.status AS consignment_status, cs.due_date, cs.sold_units, cs.returned_units,
            inv.id AS invoice_id, inv.number AS invoice_number, inv.total_amount AS invoice_total,
            (SELECT COUNT(*) FROM reviews rv2 WHERE rv2.commission_id = c.id AND rv2.author_id = c.venue_id) AS venue_review_count
     FROM commissions c
     JOIN requests rq ON rq.id = c.request_id
     JOIN venue_profiles vp ON vp.user_id = c.venue_id
     JOIN users uv ON uv.id = c.venue_id
     JOIN users ua ON ua.id = c.artisan_id
     LEFT JOIN artisan_profiles ap ON ap.user_id = c.artisan_id
     JOIN lots l ON l.id = c.lot_id
     JOIN recipe_versions rv ON rv.id = l.recipe_version_id
     JOIN recipes r ON r.id = rv.recipe_id
     LEFT JOIN consignments cs ON cs.commission_id = c.id
     LEFT JOIN invoices inv ON inv.commission_id = c.id AND inv.status = "emitida"';

    public function find(int $id): ?array
    {
        return Database::first(self::BASE_SELECT . ' WHERE c.id = ?', [$id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function forVenue(int $venueId): array
    {
        return Database::select(self::BASE_SELECT . ' WHERE c.venue_id = ? ORDER BY c.created_at DESC', [$venueId]);
    }

    /** @return array<int, array<string, mixed>> */
    public function forArtisan(int $artisanId): array
    {
        return Database::select(self::BASE_SELECT . ' WHERE c.artisan_id = ? ORDER BY c.created_at DESC', [$artisanId]);
    }

    public function consignment(int $commissionId): ?array
    {
        return Database::first('SELECT * FROM consignments WHERE commission_id = ?', [$commissionId]);
    }

    public function invoice(int $commissionId): ?array
    {
        return Database::first(
            'SELECT * FROM invoices WHERE commission_id = ? AND status = "emitida" ORDER BY id DESC LIMIT 1',
            [$commissionId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function reviews(int $commissionId): array
    {
        return Database::select(
            'SELECT rv.*, u.display_name AS author_name FROM reviews rv JOIN users u ON u.id = rv.author_id
             WHERE rv.commission_id = ? ORDER BY rv.created_at DESC',
            [$commissionId]
        );
    }

    /**
     * Comisiones completadas hace más de 72 h que el local aún no ha valorado.
     * @return array<int, array<string, mixed>>
     */
    public function pendingReviewsForVenue(int $venueId): array
    {
        return Database::select(
            'SELECT c.*, l.code AS lot_code, r.title AS recipe_title, r.slug AS recipe_slug,
                    ap.studio_name, cs.id AS consignment_id
             FROM commissions c
             JOIN lots l ON l.id = c.lot_id
             JOIN recipe_versions rv ON rv.id = l.recipe_version_id
             JOIN recipes r ON r.id = rv.recipe_id
             LEFT JOIN artisan_profiles ap ON ap.user_id = c.artisan_id
             LEFT JOIN consignments cs ON cs.commission_id = c.id
             WHERE c.venue_id = ? AND c.status = "completada"
               AND c.completed_at <= DATE_SUB(NOW(), INTERVAL 72 HOUR)
               AND NOT EXISTS (SELECT 1 FROM reviews rv2 WHERE rv2.commission_id = c.id AND rv2.author_id = ?)
             ORDER BY c.completed_at ASC',
            [$venueId, $venueId]
        );
    }

    public function stats(): array
    {
        return [
            'total' => (int) Database::value('SELECT COUNT(*) FROM commissions'),
            'completed' => (int) Database::value('SELECT COUNT(*) FROM commissions WHERE status = "completada"'),
            'open' => (int) Database::value('SELECT COUNT(*) FROM commissions WHERE status IN ("abierta","en_ejecucion")'),
            'gmv' => (float) Database::value('SELECT COALESCE(SUM(total_amount), 0) FROM commissions WHERE status = "completada"'),
            'units' => (int) Database::value('SELECT COALESCE(SUM(units), 0) FROM commissions WHERE status = "completada"'),
        ];
    }
}
