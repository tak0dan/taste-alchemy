<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repository\AuditRepository;
use App\Repository\ModerationRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Support\Nif;

/**
 * Moderación con traza de auditoría de sólo inserción. Cada decisión sobre
 * una licencia, un reporte o una sanción queda registrada en audit_log.
 */
final class ModerationService
{
    private ModerationRepository $moderation;
    private ProfileRepository $profiles;
    private UserRepository $users;
    private AuditRepository $audit;
    private NotificationService $notifications;

    public function __construct()
    {
        $this->moderation = new ModerationRepository();
        $this->profiles = new ProfileRepository();
        $this->users = new UserRepository();
        $this->audit = new AuditRepository();
        $this->notifications = new NotificationService();
    }

    public function submitVerification(int $venueId, array $data): int
    {
        $venue = $this->profiles->venue($venueId);
        if ($venue === null) {
            throw HttpException::forbidden('No tienes perfil de local.');
        }
        $pending = $this->moderation->verificationQueue('pendiente');
        foreach ($pending as $row) {
            if ((int) $row['venue_id'] === $venueId) {
                throw HttpException::conflict('Ya tienes una verificación pendiente.');
            }
        }
        $nif = Nif::normalize($data['submitted_nif'] ?? null);
        if ($nif === null || !Nif::valid($nif)) {
            throw new ValidationException(['submitted_nif' => 'El NIF/NIE/CIF no es válido.']);
        }
        $license = trim((string) ($data['submitted_license'] ?? ''));
        if ($license === '') {
            throw new ValidationException(['submitted_license' => 'Indica el número de licencia.']);
        }

        $id = $this->moderation->createVerification([
            'venue_id' => $venueId,
            'kind' => 'licencia',
            'submitted_nif' => $nif,
            'submitted_license' => $license,
            'document_ref' => trim((string) ($data['document_ref'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);
        $this->audit->log('verification.submitted', 'verification', $id, $venueId, [
            'nif_type' => Nif::type($nif),
            'license' => $license,
        ]);
        return $id;
    }

    public function approveVerification(int $moderatorId, int $verificationId, ?string $notes = null): void
    {
        $verification = $this->moderation->verification($verificationId);
        if ($verification === null) {
            throw HttpException::notFound('La verificación no existe.');
        }
        if ($verification['status'] !== 'pendiente') {
            throw HttpException::conflict('Esa verificación ya fue revisada.');
        }

        Database::transaction(function () use ($moderatorId, $verificationId, $verification, $notes): void {
            $this->moderation->resolveVerification($verificationId, 'aprobada', $moderatorId, $notes);
            $this->profiles->setLicenseStatus((int) $verification['venue_id'], 'verificada', $moderatorId);
            $this->audit->log('verification.approved', 'verification', $verificationId, $moderatorId, [
                'venue_id' => (int) $verification['venue_id'],
                'nif_masked' => mask_nif($verification['nif']),
            ]);
            $this->notifications->notify(
                (int) $verification['venue_id'],
                'verification.approved',
                'Licencia verificada',
                'Ya puedes abrir peticiones y comparar propuestas de lotes reales.',
                '/bar'
            );
        });
    }

    public function rejectVerification(int $moderatorId, int $verificationId, string $reason): void
    {
        $verification = $this->moderation->verification($verificationId);
        if ($verification === null) {
            throw HttpException::notFound('La verificación no existe.');
        }
        if ($verification['status'] !== 'pendiente') {
            throw HttpException::conflict('Esa verificación ya fue revisada.');
        }
        Database::transaction(function () use ($moderatorId, $verificationId, $verification, $reason): void {
            $this->moderation->resolveVerification($verificationId, 'rechazada', $moderatorId, $reason);
            $this->profiles->setLicenseStatus((int) $verification['venue_id'], 'rechazada', $moderatorId);
            $this->audit->log('verification.rejected', 'verification', $verificationId, $moderatorId, [
                'venue_id' => (int) $verification['venue_id'],
                'reason' => $reason,
            ]);
            $this->notifications->notify(
                (int) $verification['venue_id'],
                'verification.rejected',
                'Licencia rechazada',
                $reason,
                '/bar/verification'
            );
        });
    }

    public function resolveReport(int $moderatorId, int $reportId, string $status, string $resolution): void
    {
        if (!in_array($status, ['en_revision', 'resuelto', 'descartado'], true)) {
            throw new ValidationException(['status' => 'Estado de reporte no válido.']);
        }
        $report = $this->moderation->report($reportId);
        if ($report === null) {
            throw HttpException::notFound('El reporte no existe.');
        }
        $this->moderation->resolveReport($reportId, $status, $moderatorId, $resolution);
        $this->audit->log('report.' . $status, 'moderation_report', $reportId, $moderatorId, [
            'entity_type' => $report['entity_type'],
            'entity_id' => (int) $report['entity_id'],
            'resolution' => $resolution,
        ]);
        if ($report['reporter_id'] !== null) {
            $this->notifications->notify(
                (int) $report['reporter_id'],
                'report.resolved',
                'Tu reporte se ha revisado',
                $resolution,
                '/moderation'
            );
        }
    }

    public function sanction(int $moderatorId, int $userId, string $status, string $reason): void
    {
        if (!in_array($status, ['activo', 'suspendido'], true)) {
            throw new ValidationException(['status' => 'Sanción no válida.']);
        }
        $user = $this->users->find($userId);
        if ($user === null) {
            throw HttpException::notFound('El usuario no existe.');
        }
        Database::execute('UPDATE users SET status = ? WHERE id = ?', [$status, $userId]);
        $this->audit->log('user.' . ($status === 'suspendido' ? 'suspended' : 'reactivated'), 'user', $userId, $moderatorId, [
            'reason' => $reason,
        ]);
        $this->notifications->notify(
            $userId,
            'sanction',
            $status === 'suspendido' ? 'Cuenta suspendida' : 'Cuenta reactivada',
            $reason,
            '/'
        );
    }

    public function overview(): array
    {
        return [
            'counts' => $this->moderation->counts(),
            'queue' => $this->moderation->verificationQueue('pendiente'),
            'reports' => $this->moderation->reports(),
            'audit' => $this->audit->recent(60),
            'verified' => Database::select(
                'SELECT * FROM v_venue_trust WHERE license_status = "verificada" ORDER BY verified_at DESC LIMIT 20'
            ),
        ];
    }

    public function auditTrail(int $limit = 100): array
    {
        return $this->audit->recent($limit);
    }

    public function verification(int $id): ?array
    {
        return $this->moderation->verification($id);
    }

    public function report(int $id): ?array
    {
        return $this->moderation->report($id);
    }
}
