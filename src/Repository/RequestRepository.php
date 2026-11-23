<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class RequestRepository
{
    public function find(int $id): ?array
    {
        return Database::first(
            'SELECT rq.*, vp.venue_name, vp.slug AS venue_slug, vp.license_status, vp.territory AS venue_territory,
                    u.display_name AS venue_contact
             FROM requests rq
             JOIN venue_profiles vp ON vp.user_id = rq.venue_id
             JOIN users u ON u.id = rq.venue_id
             WHERE rq.id = ?',
            [$id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function byVenue(int $venueId): array
    {
        return Database::select(
            'SELECT rq.*,
                    (SELECT COUNT(*) FROM proposals p WHERE p.request_id = rq.id AND p.status = "pendiente") AS pending_proposals,
                    (SELECT COUNT(*) FROM proposals p2 WHERE p2.request_id = rq.id) AS total_proposals
             FROM requests rq WHERE rq.venue_id = ? ORDER BY rq.created_at DESC',
            [$venueId]
        );
    }

    /**
     * @param array{territory?:string, style?:string, q?:string, abv?:float, min_age?:int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function open(array $filters = [], int $limit = 30): array
    {
        $where = ["status = 'abierta'"];
        $params = [];

        if (!empty($filters['territory'])) {
            $where[] = 'territory LIKE ?';
            $params[] = '%' . $filters['territory'] . '%';
        }
        if (!empty($filters['style'])) {
            $where[] = 'style = ?';
            $params[] = $filters['style'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(title LIKE ? OR description LIKE ? OR venue_name LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term, $term);
        }
        if (!empty($filters['abv'])) {
            $where[] = '(abv_min IS NULL OR abv_min <= ?) AND (abv_max IS NULL OR abv_max >= ?)';
            array_push($params, (float) $filters['abv'], (float) $filters['abv']);
        }

        $limit = max(1, min(100, $limit));
        return Database::select(
            'SELECT * FROM v_open_requests WHERE ' . implode(' AND ', $where)
            . ' ORDER BY created_at DESC LIMIT ' . $limit,
            $params
        );
    }

    public function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO requests
             (venue_id, title, style, description, territory, units_wanted, abv_min, abv_max,
              target_date, exclusivity_requested, status, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['venue_id'],
                (string) $data['title'],
                (string) $data['style'],
                $data['description'] ?? null,
                (string) $data['territory'],
                (int) $data['units_wanted'],
                isset($data['abv_min']) ? (float) $data['abv_min'] : null,
                isset($data['abv_max']) ? (float) $data['abv_max'] : null,
                (string) $data['target_date'],
                !empty($data['exclusivity_requested']) ? 1 : 0,
                $data['status'] ?? 'abierta',
                $data['expires_at'] ?? null,
            ]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        Database::execute('UPDATE requests SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function stats(): array
    {
        return [
            'open' => (int) Database::value("SELECT COUNT(*) FROM requests WHERE status = 'abierta'"),
            'in_commission' => (int) Database::value("SELECT COUNT(*) FROM requests WHERE status = 'en_comision'"),
            'total' => (int) Database::value('SELECT COUNT(*) FROM requests'),
        ];
    }
}
