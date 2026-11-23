<?php
declare(strict_types=1);

namespace App\Http\Api;

use App\Calculation\RecipeEngine;
use App\Calculation\RecipePresets;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;

final class CalculatorApiController extends Controller
{
    public function calculate(Request $request): Response
    {
        $input = $request->all();
        if (isset($input['input']) && is_string($input['input'])) {
            $decoded = json_decode($input['input'], true);
            $input = is_array($decoded) ? $decoded : [];
        }

        return Response::json(['data' => RecipeEngine::calculate($input)]);
    }

    public function presets(Request $request): Response
    {
        return Response::json([
            'data' => [
                'bases' => RecipePresets::all(),
                'extractions' => RecipePresets::extractions(),
                'syrups' => RecipePresets::syrups(),
                'constants' => [
                    'sugar_ratio' => RecipeEngine::SUGAR_RATIO,
                    'water_ratio' => RecipeEngine::WATER_RATIO,
                    'juice_yield' => RecipeEngine::JUICE_YIELD,
                    'sugar_volume_factor' => RecipeEngine::SUGAR_VOLUME_FACTOR,
                    'vodka_abv' => RecipeEngine::VODKA_ABV,
                    'jar_fruit_g' => RecipeEngine::JAR_FRUIT_G,
                    'jar_volume_ml' => RecipeEngine::JAR_VOLUME_ML,
                    'bottle_ml' => RecipeEngine::BOTTLE_ML,
                ],
            ],
        ]);
    }
}
