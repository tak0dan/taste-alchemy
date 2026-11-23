<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Calculation\RecipeEngine;
use App\Calculation\RecipePresets;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;

final class CalculatorController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('calculator', [
            'title' => 'Constructor de licores',
            'presets' => RecipePresets::all(),
            'extractions' => RecipePresets::extractions(),
            'syrups' => RecipePresets::syrups(),
            'initial' => RecipeEngine::calculate([]),
            'initialInput' => [],
        ]);
    }

    public function calculate(Request $request): Response
    {
        $input = $request->all();
        if (isset($input['input']) && is_string($input['input'])) {
            $decoded = json_decode($input['input'], true);
            $input = is_array($decoded) ? $decoded : [];
        }
        $result = RecipeEngine::calculate($input);

        if ($request->wantsJson() || $request->header('accept') === 'application/json') {
            return Response::json($result);
        }

        return $this->view('calculator', [
            'title' => 'Constructor de licores',
            'presets' => RecipePresets::all(),
            'extractions' => RecipePresets::extractions(),
            'syrups' => RecipePresets::syrups(),
            'initial' => $result,
            'initialInput' => $input,
        ]);
    }
}
