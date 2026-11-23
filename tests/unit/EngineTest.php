<?php
declare(strict_types=1);

use App\Calculation\RecipeEngine;

// Vectores dorados compartidos con la prueba de JS (tests/js/golden.test.js).
$vectors = json_decode(file_get_contents(BASE_PATH . '/tests/vectors/engine.json'), true);

foreach ($vectors['engine'] as $vector) {
    it('Vector dorado: ' . $vector['name'], function () use ($vector) {
        $r = RecipeEngine::calculate($vector['input']);
        $e = $vector['expect'];
        near($e['abv_pct'], $r['abv_pct'], 0.01, 'ABV');
        near($e['alcohol_ml'], $r['alcohol_ml'], 0.5, 'alcohol');
        near($e['final_ml'], $r['volumes']['final_ml'], 0.5, 'volumen final');
        near($e['net_vodka_ml'], $r['volumes']['net_vodka_ml'], 0.5, 'vodka neto');
        near($e['juice_ml'], $r['volumes']['juice_ml'], 0.5, 'jugo');
        near($e['sugar_volume_ml'], $r['volumes']['sugar_volume_ml'], 0.5, 'volumen azúcar');
        near($e['water_ml'], $r['volumes']['water_ml'], 0.5, 'agua');
        near($e['sugar_g'], $r['ingredients']['sugar_g'], 0.5, 'azúcar');
        near($e['sugar_gl'], $r['sugar_gl'], 0.5, 'g/L');
        near($e['brix'], $r['brix'], 0.05, 'Brix');
        near($e['freezing_point_c'], $r['freeze']['freezing_point_c'], 0.05, 'congelación');
    });
}

it('Motor: los dos modos son equivalentes (volumen ↔ ingredientes)', function () {
    $r = RecipeEngine::calculate([
        'base' => 'cereza', 'mode' => 'volumen', 'base_abv_pct' => 40,
        'desired_volume_ml' => 1660, 'extraction' => 'hybrid',
        'sugar_ratio' => 0.4, 'water_ratio' => 0.5,
    ]);
    near(0.0, $r['equivalents']['delta_abv'], 0.01, 'delta ABV');
    near(0.0, $r['equivalents']['delta_volume_ml'], 1.0, 'delta volumen');
});

it('Motor: escalar ×2 duplica volumen y conserva la graduación', function () {
    $input = [
        'base' => 'cereza', 'mode' => 'ingredientes', 'base_abv_pct' => 40,
        'vodka_ml' => 1000, 'ingredient_g' => 1000, 'extraction' => 'hybrid',
        'sugar_ratio' => 0.4, 'water_ratio' => 0.5,
    ];
    $base = RecipeEngine::calculate($input);
    $scaled = RecipeEngine::scale($input, 2.0);
    near($base['volumes']['final_ml'] * 2, $scaled['volumes']['final_ml'], 1.0);
    near($base['abv_pct'], $scaled['abv_pct'], 0.01);
});

it('Motor: el vector dorado del prototipo da 1660 mL y ~22,17 %', function () {
    $r = RecipeEngine::calculate([
        'base' => 'cereza', 'mode' => 'ingredientes', 'base_abv_pct' => 40,
        'vodka_ml' => 1000, 'ingredient_g' => 1000, 'desired_volume_ml' => 2000,
        'extraction' => 'hybrid', 'sugar_ratio' => 0.4, 'water_ratio' => 0.5,
    ]);
    near(1660.0, $r['volumes']['final_ml'], 0.5);
    near(22.17, $r['abv_pct'], 0.05);
    near(368.0, $r['alcohol_ml'], 0.5);
});

it('Motor: warns de graduación fuera de rango de guarda', function () {
    $low = RecipeEngine::calculate([
        'base' => 'cereza', 'mode' => 'ingredientes', 'base_abv_pct' => 40,
        'vodka_ml' => 100, 'ingredient_g' => 2000, 'extraction' => 'limoncello',
    ]);
    ok(count($low['warnings']) > 0, 'debería avisar de graduación baja');
});