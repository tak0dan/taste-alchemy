<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Service\AuthService;
use App\Service\MarketplaceService;

final class CommissionController extends Controller
{
    public function show(Request $request, array $params): Response
    {
        $detail = (new MarketplaceService())->commissionDetail((int) $params['id'], $this->userId());

        return $this->view('commissions/show', [
            'title' => 'Comisión #' . $detail['commission']['id'],
            'detail' => $detail,
        ]);
    }

    public function closeConsignment(Request $request, array $params): Response
    {
        $commissionId = (int) $params['id'];
        $consignment = \App\Core\Database::first(
            'SELECT id FROM consignments WHERE commission_id = ?',
            [$commissionId]
        );
        if ($consignment === null) {
            throw \App\Core\HttpException::notFound('Esta comisión no tiene consignación.');
        }

        $sold = (int) $request->input('sold_units', 0);
        $returned = (int) $request->input('returned_units', 0);
        $invoiceId = (new MarketplaceService())->closeConsignment(
            $this->userId(),
            (int) $consignment['id'],
            $sold,
            $returned
        );

        return $this->redirect(
            '/commissions/' . $commissionId,
            'success',
            $invoiceId !== null
                ? 'Consignación cerrada y factura emitida.'
                : 'Consignación cerrada sin unidades vendidas; no se emite factura.'
        );
    }

    public function complete(Request $request, array $params): Response
    {
        (new MarketplaceService())->completeDelivery($this->userId(), (int) $params['id']);

        return $this->redirect(
            '/commissions/' . (int) $params['id'],
            'success',
            'Entrega confirmada. La comisión queda completada y empieza la reflexión de 72 horas.'
        );
    }
}
