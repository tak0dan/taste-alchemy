<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class AuditRepository
{
    public function log(string $action, string $entityType, ?int $entityId = null, ?int $actorId = null, array $payload = [], ?string $ip = null): int
    {
        return Database::insert(
            'INSERT INTO audit_log (actor_id, action, entity_type, entity_id, payload_json, ip)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $actorId,
                $action,
                $entityType,
                $entityId,
                $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
                $ip,
            ]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 50, ?string $entityType = null, ?int $entityId = null): array
    {
        $limit = max(1, min(500, $limit));
        $where = ['1 = 1'];
        $params = [];
        if ($entityType !== null) {
            $where[] = 'a.entity_type = ?';
            $params[] = $entityType;
        }
        if ($entityId !== null) {
            $where[] = 'a.entity_id = ?';
            $params[] = $entityId;
        }
        return Database::select(
            'SELECT a.*, u.display_name AS actor_name
             FROM audit_log a LEFT JOIN users u ON u.id = a.actor_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.id DESC LIMIT ' . $limit,
            $params
        );
    }

    public function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM audit_log');
    }
}
