<?php
declare(strict_types=1);

// Genera tests/vectors/engine.json a partir del motor PHP. Se ejecuta una vez
// para congelar los vectores dorados; PHP y JS los consumen por igual.
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/src/autoload.php';

use App\Calculation\RecipeEngine;
use App\Calculation\SugarModel;
use App\Calculation\SlushyPredictor;
use App\Calculation\DilutionPlanner;

$cases = [
    ['name' => 'cereza_hybrid_golden', 'input' => [
        'base' => 'cereza', 'mode' => 'ingredientes', 'base_abv_pct' => 40,
        'vodka_ml' => 1000, 'ingredient_g' => 1000, 'desired_volume_ml' => 2000,
        'extraction' => 'hybrid', 'sugar_ratio' => 0.4, 'water_ratio' => 0.5,
    ]],
    ['name' => 'cereza_limoncello', 'input' => [
        'base' => 'cereza', 'mode' => 'ingredientes', 'base_abv_pct' => 40,
        'vodka_ml' => 1000, 'ingredient_g' => 1000, 'extraction' => 'limoncello',
        'sugar_ratio' => 0.4, 'water_ratio' => 0.5,
    ]],
    ['name' => 'cereza_hot', 'input' => [
        'base' => 'cereza', 'mode' => 'ingredientes', 'base_abv_pct' => 40,
        'vodka_ml' => 1000, 'ingredient_g' => 1000, 'extraction' => 'hot',
        'sugar_ratio' => 0.4, 'water_ratio' => 0.5,
    ]],
    ['name' => 'hibisco_limoncello', 'input' => [
        'base' => 'hibisco', 'mode' => 'ingredientes', 'base_abv_pct' => 40,
        'vodka_ml' => 700, 'ingredient_g' => 60, 'extraction' => 'limoncello',
        'sugar_ratio' => 0.38, 'water_ratio' => 0.5,
    ]],
    ['name' => 'cereza_blend', 'input' => [
        'base' => 'cereza', 'mode' => 'ingredientes', 'base_abv_pct' => 40,
        'vodka_ml' => 1000, 'ingredient_g' => 1000, 'extraction' => 'hybrid',
        'sugar_ratio' => 0.4, 'water_ratio' => 0.5, 'blend_enabled' => true, 'blend_pct' => 15,
    ]],
    ['name' => 'volumen_equivalence', 'input' => [
        'base' => 'cereza', 'mode' => 'volumen', 'base_abv_pct' => 40,
        'desired_volume_ml' => 1660, 'extraction' => 'hybrid',
        'sugar_ratio' => 0.4, 'water_ratio' => 0.5,
    ]],
];

$vectors = [];
foreach ($cases as $case) {
    $r = RecipeEngine::calculate($case['input']);
    $vectors[] = [
        'name' => $case['name'],
        'input' => $case['input'],
        'expect' => [
            'abv_pct' => $r['abv_pct'],
            'alcohol_ml' => $r['alcohol_ml'],
            'final_ml' => $r['volumes']['final_ml'],
            'net_vodka_ml' => $r['volumes']['net_vodka_ml'],
            'juice_ml' => $r['volumes']['juice_ml'],
            'sugar_volume_ml' => $r['volumes']['sugar_volume_ml'],
            'water_ml' => $r['volumes']['water_ml'],
            'sugar_g' => $r['ingredients']['sugar_g'],
            'sugar_gl' => $r['sugar_gl'],
            'brix' => $r['brix'],
            'freezing_point_c' => $r['freeze']['freezing_point_c'],
        ],
    ];
}

$scalars = [
    'sugar' => [
        ['brix' => 20, 'sg' => SugarModel::specificGravity(20), 'gl' => SugarModel::brixToSugarGl(20)],
        ['brix' => 0, 'sg' => SugarModel::specificGravity(0), 'gl' => SugarModel::brixToSugarGl(0)],
    ],
    'slushy' => [
        ['abv' => 40, 'brix' => 0, 'fp' => SlushyPredictor::freezingPointC(40, 0)],
        ['abv' => 20, 'brix' => 0, 'fp' => SlushyPredictor::freezingPointC(20, 0)],
        ['abv' => 22.17, 'brix' => 24, 'fp' => SlushyPredictor::freezingPointC(22.17, 24)],
    ],
    'dilution' => [
        'pearson_40_0_30' => DilutionPlanner::pearson(40, 0, 30),
        'dilute_1000_40_to_20' => DilutionPlanner::dilute(1000, 40, 20),
    ],
];

$out = ['engine' => $vectors, 'scalars' => $scalars];
$path = BASE_PATH . '/tests/vectors/engine.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
echo "Escritos " . count($vectors) . " vectores de motor + escalares en {$path}\n";