<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Service\LotService;

final class LotController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'territory' => trim((string) $request->query('territory', '')),
            'min_coherence' => (int) $request->query('min_coherence', 0),
            'status' => (string) $request->query('status', ''),
            'sort' => (string) $request->query('sort', 'coherence'),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $result = (new LotService())->search($filters, $page, 9);

        return $this->view('lots/index', [
            'title' => 'Lotes con evidencia física',
            'result' => $result,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $detail = (new LotService())->detail((int) $params['id']);

        return $this->view('lots/show', [
            'title' => 'Lote ' . $detail['lot']['code'],
            'detail' => $detail,
        ]);
    }
}
