<?php
declare(strict_types=1);

namespace App\Http\Api;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repository\RecipeRepository;
use App\Service\RecipeService;

final class RecipeApiController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'style' => (string) $request->query('style', ''),
            'territory' => trim((string) $request->query('territory', '')),
            'abv_min' => $request->query('abv_min', ''),
            'abv_max' => $request->query('abv_max', ''),
            'sort' => (string) $request->query('sort', 'recent'),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 12)));
        $result = (new RecipeService())->search($filters, $page, $perPage);

        foreach ($result['items'] as &$item) {
            $item['tags'] = json_data($item['tags_json'] ?? null);
            unset($item['tags_json']);
        }

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
        $detail = (new RecipeService())->detail((string) $params['slug']);

        return Response::json([
            'data' => [
                'recipe' => $detail['recipe'],
                'versions' => $detail['versions'],
                'current_version' => $detail['current_version'],
                'computed' => $detail['computed'],
                'lots' => $detail['lots'],
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requireRole('artesano');
        $id = (new RecipeService())->create($this->userId(), $request->all());
        $recipe = (new RecipeRepository())->findById($id);

        return Response::json([
            'data' => [
                'id' => $id,
                'slug' => $recipe['slug'] ?? null,
                'url' => url('/recipes/' . ($recipe['slug'] ?? '')),
            ],
        ], 201, ['Location' => url('/api/v1/recipes/' . ($recipe['slug'] ?? ''))]);
    }

    public function fork(Request $request, array $params): Response
    {
        $this->requireRole('artesano');
        $id = (new RecipeService())->fork((int) $params['id'], $this->userId(), $request->input('title'));
        $recipe = (new RecipeRepository())->findById($id);

        return Response::json([
            'data' => [
                'id' => $id,
                'slug' => $recipe['slug'] ?? null,
                'forked_from' => (int) $params['id'],
            ],
        ], 201);
    }

    public function storeVersion(Request $request, array $params): Response
    {
        $this->requireRole('artesano');
        $versionId = (new RecipeService())->addVersion((int) $params['id'], $this->userId(), $request->all());

        return Response::json([
            'data' => ['version_id' => $versionId, 'recipe_id' => (int) $params['id']],
        ], 201);
    }

    public function scale(Request $request, array $params): Response
    {
        $factor = max(0.001, min(1000, (float) $request->query('factor', 2)));
        $computed = (new RecipeService())->scale((int) $params['id'], $factor);

        return Response::json([
            'data' => [
                'factor' => $factor,
                'computed' => $computed,
            ],
        ]);
    }
}
