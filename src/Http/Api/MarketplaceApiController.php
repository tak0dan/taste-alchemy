<?php
declare(strict_types=1);

namespace App\Http\Api;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Service\MarketplaceService;

final class MarketplaceApiController extends Controller
{
    public function requests(Request $request): Response
    {
        $filters = [
            'territory' => trim((string) $request->query('territory', '')),
            'style' => (string) $request->query('style', ''),
            'q' => trim((string) $request->query('q', '')),
            'abv' => $request->query('abv', ''),
        ];
        $limit = max(1, min(100, (int) $request->query('limit', 30)));

        return Response::json(['data' => (new MarketplaceService())->openRequests($filters, $limit)]);
    }

    public function storeRequest(Request $request): Response
    {
        $this->requireRole('local');
        $id = (new MarketplaceService())->createRequest($this->userId(), $request->all());

        return Response::json([
            'data' => ['id' => $id, 'url' => url('/requests/' . $id)],
        ], 201, ['Location' => url('/api/v1/requests/' . $id)]);
    }

    public function propose(Request $request, array $params): Response
    {
        $this->requireRole('artesano');
        $id = (new MarketplaceService())->propose($this->userId(), (int) $params['id'], $request->all());

        return Response::json(['data' => ['proposal_id' => $id]], 201);
    }

    public function accept(Request $request, array $params): Response
    {
        $this->requireRole('local');
        $commissionId = (new MarketplaceService())->accept(
            $this->userId(),
            (int) $params['id'],
            (string) $request->input('mode', 'venta')
        );

        return Response::json([
            'data' => [
                'commission_id' => $commissionId,
                'url' => url('/api/v1/commissions/' . $commissionId),
            ],
        ], 201, ['Location' => url('/api/v1/commissions/' . $commissionId)]);
    }

    public function commission(Request $request, array $params): Response
    {
        $detail = (new MarketplaceService())->commissionDetail((int) $params['id'], $this->userId());

        return Response::json(['data' => $detail]);
    }

    public function storeReview(Request $request, array $params): Response
    {
        $this->requireRole('local');
        $reviewId = (new MarketplaceService())->submitReview(
            $this->userId(),
            (int) $params['id'],
            $request->all()
        );

        return Response::json(['data' => ['review_id' => $reviewId]], 201);
    }

    public function pendingReviews(Request $request): Response
    {
        $this->requireRole('local');

        return Response::json([
            'data' => (new MarketplaceService())->pendingReviews($this->userId()),
        ]);
    }

    public function completeDelivery(Request $request, array $params): Response
    {
        $this->requireRole('local');
        (new MarketplaceService())->completeDelivery($this->userId(), (int) $params['id']);

        return Response::json(['data' => ['commission_id' => (int) $params['id'], 'status' => 'completada']]);
    }

    public function closeConsignment(Request $request, array $params): Response
    {
        $invoiceId = (new MarketplaceService())->closeConsignment(
            $this->userId(),
            (int) $params['id'],
            (int) $request->input('sold_units', 0),
            (int) $request->input('returned_units', 0)
        );

        return Response::json([
            'data' => [
                'consignment_id' => (int) $params['id'],
                'invoice_id' => $invoiceId,
            ],
        ]);
    }

    public function report(Request $request): Response
    {
        $id = (new MarketplaceService())->report($this->userId(), $request->all());
        return Response::json(['data' => ['report_id' => $id]], 201);
    }
}
