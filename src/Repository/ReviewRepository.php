<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class ReviewRepository
{
    /** @return array<int, array<string, mixed>> */
    public function forLot(int $lotId): array
    {
        return Database::select(
            'SELECT rv.*, u.display_name AS author_name, ap.studio_name, vp.venue_name,
                    (SELECT COUNT(*) FROM user_roles ur JOIN roles ro ON ro.id = ur.role_id
                      WHERE ur.user_id = rv.author_id AND ro.code = "comisario") AS is_curator
             FROM reviews rv
             JOIN users u ON u.id = rv.author_id
             LEFT JOIN artisan_profiles ap ON ap.user_id = rv.author_id
             LEFT JOIN venue_profiles vp ON vp.user_id = rv.author_id
             WHERE rv.lot_id = ? AND rv.status = "publicada"
             ORDER BY rv.kind = "comisario" DESC, rv.published_at DESC',
            [$lotId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        return Database::select(
            'SELECT rv.*, u.display_name AS author_name, r.title AS recipe_title, r.slug AS recipe_slug,
                    l.code AS lot_code, vp.venue_name
             FROM reviews rv
             JOIN users u ON u.id = rv.author_id
             JOIN lots l ON l.id = rv.lot_id
             JOIN recipe_versions rvv ON rvv.id = l.recipe_version_id
             JOIN recipes r ON r.id = rvv.recipe_id
             LEFT JOIN venue_profiles vp ON vp.user_id = rv.author_id
             WHERE rv.status = "publicada" AND rv.kind = "local"
             ORDER BY rv.published_at DESC LIMIT ' . $limit
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function byAuthor(int $authorId): array
    {
        return Database::select(
            'SELECT rv.*, l.code AS lot_code, r.title AS recipe_title, r.slug AS recipe_slug
             FROM reviews rv
             JOIN lots l ON l.id = rv.lot_id
             JOIN recipe_versions rvv ON rvv.id = l.recipe_version_id
             JOIN recipes r ON r.id = rvv.recipe_id
             WHERE rv.author_id = ? ORDER BY rv.created_at DESC',
            [$authorId]
        );
    }

    public function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO reviews
             (commission_id, author_id, kind, lot_id, score_aroma, score_texture, score_balance, score_finish,
              score_total, body, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "publicada")',
            [
                $data['commission_id'] ?? null,
                (int) $data['author_id'],
                (string) ($data['kind'] ?? 'local'),
                (int) $data['lot_id'],
                $data['score_aroma'] ?? null,
                $data['score_texture'] ?? null,
                $data['score_balance'] ?? null,
                $data['score_finish'] ?? null,
                $data['score_total'] ?? null,
                (string) $data['body'],
            ]
        );
    }

    public function averageForLot(int $lotId): ?float
    {
        $value = Database::value(
            'SELECT AVG(score_total) FROM reviews WHERE lot_id = ? AND status = "publicada"',
            [$lotId]
        );
        return $value === null ? null : (float) $value;
    }

    public function countPublished(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM reviews WHERE status = "publicada"');
    }
}
