<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Calculation\RecipeEngine;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repository\RecipeRepository;
use App\Service\AuthService;
use App\Service\RecipeService;

final class RecipeController extends Controller
{
    public function index(Request $request): Response
    {
        $service = new RecipeService();
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'style' => (string) $request->query('style', ''),
            'territory' => trim((string) $request->query('territory', '')),
            'abv_min' => $request->query('abv_min', ''),
            'abv_max' => $request->query('abv_max', ''),
            'sort' => (string) $request->query('sort', 'recent'),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $result = $service->search($filters, $page, 9);

        return $this->view('recipes/index', [
            'title' => 'Repositorio de recetas',
            'result' => $result,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $service = new RecipeService();
        $detail = $service->detail((string) $params['slug']);

        return $this->view('recipes/show', [
            'title' => $detail['recipe']['title'],
            'detail' => $detail,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requireRole('artesano');
        return $this->view('recipes/create', [
            'title' => 'Nueva receta',
            'recipePresets' => \App\Calculation\RecipePresets::all(),
            'extractions' => \App\Calculation\RecipePresets::extractions(),
            'syrups' => \App\Calculation\RecipePresets::syrups(),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requireRole('artesano');
        $id = (new RecipeService())->create($this->userId(), $request->all());
        $recipe = (new RecipeRepository())->findById($id);

        return $this->redirect(
            '/recipes/' . ($recipe['slug'] ?? ''),
            'success',
            'Receta publicada con su primera versión ejecutable.'
        );
    }

    public function fork(Request $request, array $params): Response
    {
        $this->requireRole('artesano');
        $id = (new RecipeService())->fork((int) $params['id'], $this->userId(), $request->input('title'));
        $recipe = (new RecipeRepository())->findById($id);

        return $this->redirect(
            '/recipes/' . ($recipe['slug'] ?? ''),
            'success',
            'Bifurcación creada. La receta original conserva su historial.'
        );
    }

    public function storeVersion(Request $request, array $params): Response
    {
        $this->requireRole('artesano');
        $recipeId = (int) $params['id'];
        (new RecipeService())->addVersion($recipeId, $this->userId(), $request->all());
        $recipe = (new RecipeRepository())->findById($recipeId);

        return $this->redirect(
            '/recipes/' . ($recipe['slug'] ?? ''),
            'success',
            'Nueva versión publicada. Las anteriores siguen siendo consultables.'
        );
    }

    public function scale(Request $request, array $params): Response
    {
        $factor = (float) $request->query('factor', 2);
        $computed = (new RecipeService())->scale((int) $params['id'], $factor);
        return Response::json([
            'factor' => $factor,
            'computed' => $computed,
        ]);
    }
}
