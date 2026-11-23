<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Service\ModerationService;

final class ModerationController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('moderation/index', [
            'title' => 'Moderación',
            'overview' => (new ModerationService())->overview(),
        ]);
    }

    public function approve(Request $request, array $params): Response
    {
        (new ModerationService())->approveVerification(
            $this->userId(),
            (int) $params['id'],
            trim((string) $request->input('notes', '')) ?: null
        );
        return $this->redirect('/moderation', 'success', 'Licencia aprobada. El local ya puede pedir.');
    }

    public function reject(Request $request, array $params): Response
    {
        (new ModerationService())->rejectVerification(
            $this->userId(),
            (int) $params['id'],
            trim((string) $request->input('reason', 'Documentación incompleta'))
        );
        return $this->redirect('/moderation', 'info', 'Verificación rechazada con motivo registrado.');
    }

    public function resolveReport(Request $request, array $params): Response
    {
        (new ModerationService())->resolveReport(
            $this->userId(),
            (int) $params['id'],
            (string) $request->input('status', 'resuelto'),
            trim((string) $request->input('resolution', 'Revisado'))
        );
        return $this->redirect('/moderation', 'success', 'Reporte actualizado.');
    }

    public function sanction(Request $request, array $params): Response
    {
        (new ModerationService())->sanction(
            $this->userId(),
            (int) $params['id'],
            (string) $request->input('status', 'suspendido'),
            trim((string) $request->input('reason', 'Incumplimiento de normas'))
        );
        return $this->redirect('/moderation', 'info', 'Sanción aplicada y auditada.');
    }
}
