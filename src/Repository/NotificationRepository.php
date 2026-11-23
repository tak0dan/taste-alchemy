<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class NotificationRepository
{
    public function create(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): int
    {
        return Database::insert(
            'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)',
            [$userId, $type, $title, $body, $link]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function forUser(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return Database::select(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $limit,
            [$userId]
        );
    }

    public function unreadCount(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    public function markAllRead(int $userId): void
    {
        Database::execute('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }
}
