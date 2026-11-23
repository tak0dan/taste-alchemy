<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repository\LotRepository;
use App\Repository\ReviewRepository;
use App\Service\MarketplaceService;

final class CuratorController extends Controller
{
    public function index(Request $request): Response
    {
        $lots = new LotRepository();

        return $this->view('curator/index', [
            'title' => 'Mesa del comisario',
            'lots' => $lots->publicList(['min_coherence' => 70, 'sort' => 'coherence'], 24),
            'recent' => (new ReviewRepository())->recent(10),
            'stats' => $lots->stats(),
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $lotId = (int) $params['id'];
        $detail = (new \App\Service\LotService())->detail($lotId);

        return $this->view('curator/show', [
            'title' => 'Cata del lote ' . $detail['lot']['code'],
            'detail' => $detail,
        ]);
    }

    public function store(Request $request, array $params): Response
    {
        (new MarketplaceService())->submitTasting($this->userId(), (int) $params['id'], $request->all());
        return $this->redirect(
            '/lots/' . (int) $params['id'],
            'success',
            'Cata publicada. Queda vinculada al lote y a tu firma de comisario.'
        );
    }
}
