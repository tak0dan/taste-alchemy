<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class ModerationRepository
{
    /** @return array<int, array<string, mixed>> */
    public function verificationQueue(string $status = 'pendiente'): array
    {
        return Database::select(
            'SELECT vr.*, vp.venue_name, vp.slug AS venue_slug, vp.license_status, vp.territory,
                    u.display_name, u.email, u.nif
             FROM verification_requests vr
             JOIN venue_profiles vp ON vp.user_id = vr.venue_id
             JOIN users u ON u.id = vr.venue_id
             WHERE vr.status = ?
             ORDER BY vr.submitted_at ASC',
            [$status]
        );
    }

    public function verification(int $id): ?array
    {
        return Database::first(
            'SELECT vr.*, vp.venue_name, vp.slug AS venue_slug, vp.license_status, vp.territory,
                    u.display_name, u.email, u.nif
             FROM verification_requests vr
             JOIN venue_profiles vp ON vp.user_id = vr.venue_id
             JOIN users u ON u.id = vr.venue_id
             WHERE vr.id = ?',
            [$id]
        );
    }

    public function latestVerificationForVenue(int $venueId): ?array
    {
        return Database::first(
            'SELECT * FROM verification_requests WHERE venue_id = ? ORDER BY submitted_at DESC LIMIT 1',
            [$venueId]
        );
    }

    public function createVerification(array $data): int
    {
        return Database::insert(
            'INSERT INTO verification_requests (venue_id, kind, submitted_nif, submitted_license, document_ref, notes)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                (int) $data['venue_id'],
                (string) ($data['kind'] ?? 'licencia'),
                $data['submitted_nif'] ?? null,
                $data['submitted_license'] ?? null,
                $data['document_ref'] ?? null,
                $data['notes'] ?? null,
            ]
        );
    }

    public function resolveVerification(int $id, string $status, int $reviewerId, ?string $notes = null): void
    {
        Database::execute(
            'UPDATE verification_requests
             SET status = ?, reviewed_by = ?, reviewed_at = NOW(), notes = COALESCE(?, notes)
             WHERE id = ? AND status = "pendiente"',
            [$status, $reviewerId, $notes, $id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function reports(?string $status = null): array
    {
        $where = '1 = 1';
        $params = [];
        if ($status !== null) {
            $where = 'mr.status = ?';
            $params[] = $status;
        }
        return Database::select(
            'SELECT mr.*, u.display_name AS reporter_name, r.display_name AS resolver_name
             FROM moderation_reports mr
             LEFT JOIN users u ON u.id = mr.reporter_id
             LEFT JOIN users r ON r.id = mr.resolved_by
             WHERE ' . $where . '
             ORDER BY FIELD(mr.status, "abierto","en_revision","resuelto","descartado"), mr.created_at ASC',
            $params
        );
    }

    public function report(int $id): ?array
    {
        return Database::first('SELECT * FROM moderation_reports WHERE id = ?', [$id]);
    }

    public function createReport(array $data): int
    {
        return Database::insert(
            'INSERT INTO moderation_reports (reporter_id, entity_type, entity_id, reason, details, status)
             VALUES (?, ?, ?, ?, ?, "abierto")',
            [
                $data['reporter_id'] ?? null,
                (string) $data['entity_type'],
                (int) $data['entity_id'],
                (string) $data['reason'],
                $data['details'] ?? null,
            ]
        );
    }

    public function resolveReport(int $id, string $status, int $resolverId, ?string $resolution = null): void
    {
        Database::execute(
            'UPDATE moderation_reports
             SET status = ?, resolved_by = ?, resolved_at = NOW(), resolution = ?
             WHERE id = ?',
            [$status, $resolverId, $resolution, $id]
        );
    }

    public function counts(): array
    {
        return [
            'pending_verifications' => (int) Database::value('SELECT COUNT(*) FROM verification_requests WHERE status = "pendiente"'),
            'open_reports' => (int) Database::value('SELECT COUNT(*) FROM moderation_reports WHERE status IN ("abierto","en_revision")'),
            'audit_entries' => (int) Database::value('SELECT COUNT(*) FROM audit_log'),
        ];
    }
}
