<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Service\AuthService;
use App\Service\LotService;
use App\Service\MarketplaceService;

final class MarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $market = new MarketplaceService();
        $lots = new LotService();
        $filters = [
            'territory' => trim((string) $request->query('territory', '')),
            'style' => (string) $request->query('style', ''),
            'q' => trim((string) $request->query('q', '')),
        ];

        return $this->view('marketplace', [
            'title' => 'Mercado de lotes reales',
            'requests' => $market->openRequests($filters, 24),
            'listings' => $lots->publicListings(9),
            'filters' => $filters,
        ]);
    }

    public function requestShow(Request $request, array $params): Response
    {
        $market = new MarketplaceService();
        $viewerId = AuthService::id() ?? 0;
        $requestId = (int) $params['id'];
        $detail = $market->requestDetail($requestId, $viewerId, false);

        return $this->view('requests/show', [
            'title' => $detail['request']['title'],
            'detail' => $detail,
        ]);
    }
}
