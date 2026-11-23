<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\AuditRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;

/**
 * Derechos de acceso y supresión. La supresión se implementa como anonimización
 * porque las facturas y la traza de auditoría tienen obligación de conservación;
 * se eliminan los datos personales y se mantiene el registro mercantil.
 */
final class PrivacyService
{
    private UserRepository $users;
    private ProfileRepository $profiles;
    private AuditRepository $audit;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->profiles = new ProfileRepository();
        $this->audit = new AuditRepository();
    }

    public function export(int $userId): array
    {
        $data = $this->users->export($userId);
        $data['reviews_written'] = $data['reviews'] ?? [];
        $this->audit->log('privacy.export', 'user', $userId, $userId);
        return $data;
    }

    public function delete(int $userId): void
    {
        $this->profiles->anonymizeArtisan($userId);
        $this->profiles->anonymizeVenue($userId);
        $this->users->anonymize($userId);
        \App\Core\Database::execute('DELETE FROM notifications WHERE user_id = ?', [$userId]);
        $this->audit->log('privacy.deleted', 'user', $userId, $userId, [
            'mode' => 'anonymization',
            'retained' => ['invoices', 'audit_log', 'commissions'],
        ]);
        AuthService::logout();
    }
}
