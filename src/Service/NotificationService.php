<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\NotificationRepository;

final class NotificationService
{
    private NotificationRepository $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationRepository();
    }

    public function notify(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        $this->notifications->create($userId, $type, $title, $body, $link);
    }

    public function forUser(int $userId, int $limit = 20): array
    {
        return $this->notifications->forUser($userId, $limit);
    }

    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }

    public function markAllRead(int $userId): void
    {
        $this->notifications->markAllRead($userId);
    }
}
