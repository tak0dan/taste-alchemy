<?php
declare(strict_types=1);

namespace App\Http\Api;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Service\LotService;

final class LotApiController extends Controller
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
        $perPage = max(1, min(50, (int) $request->query('per_page', 12)));
        $result = (new LotService())->search($filters, $page, $perPage);

        return Response::json([
            'data' => $result['items'],
            'meta' => [
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'pages' => $result['pages'],
            ],
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $detail = (new LotService())->detail((int) $params['id']);

        return Response::json([
            'data' => [
                'lot' => $detail['lot'],
                'readings' => $detail['readings'],
                'verdict' => $detail['verdict'],
                'breakdown' => $detail['breakdown'],
                'verdict_history' => $detail['verdict_history'],
                'listing' => $detail['listing'],
                'reviews' => $detail['reviews'],
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requireRole('artesano');
        $id = (new LotService())->create($this->userId(), $request->all());

        return Response::json([
            'data' => ['id' => $id, 'url' => url('/api/v1/lots/' . $id)],
        ], 201, ['Location' => url('/api/v1/lots/' . $id)]);
    }

    public function storeReading(Request $request, array $params): Response
    {
        $this->requireRole('artesano');
        $result = (new LotService())->addReading($this->userId(), (int) $params['id'], $request->all());

        return Response::json(['data' => $result], 201);
    }

    public function verdict(Request $request, array $params): Response
    {
        $verdict = (new LotService())->verdict((int) $params['id']);
        return Response::json(['data' => $verdict]);
    }
}
