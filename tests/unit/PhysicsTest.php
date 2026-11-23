<?php
declare(strict_types=1);

use App\Calculation\SugarModel;
use App\Calculation\SlushyPredictor;
use App\Calculation\DilutionPlanner;
use App\Calculation\AbvEstimator;

// --- SugarModel -------------------------------------------------------------
it('Azúcar: gravedad específica SG = 1 + 0,004·Brix', function () {
    near(1.08, SugarModel::specificGravity(20), 1e-9);
    near(1.0, SugarModel::specificGravity(0), 1e-9);
});

it('Azúcar: Brix → g/L y vuelta (inversa iterativa)', function () {
    near(216.0, SugarModel::brixToSugarGl(20), 1e-6);
    near(20.0, SugarModel::sugarGlToBrix(216.0), 0.05);
    near(0.0, SugarModel::sugarGlToBrix(0), 1e-9);
});

it('Azúcar: volumen aparente 0,15 mL/g (constante del prototipo)', function () {
    near(60.0, SugarModel::dissolvedVolumeMl(400), 1e-9);
});

it('Azúcar: clases de dulzor', function () {
    eq('seco', SugarModel::sweetnessClass(20));
    eq('semiseco', SugarModel::sweetnessClass(50));
    eq('dulce', SugarModel::sweetnessClass(120));
    eq('muy dulce', SugarModel::sweetnessClass(250));
});

// --- SlushyPredictor --------------------------------------------------------
it('Frío: referencias de punto de congelación', function () {
    near(-22.24, SlushyPredictor::freezingPointC(40, 0), 0.01);
    near(-9.76, SlushyPredictor::freezingPointC(20, 0), 0.01);
    near(-4.54, SlushyPredictor::freezingPointC(10, 0), 0.01);
});

it('Frío: margen de congelador a −18 °C', function () {
    ok(SlushyPredictor::freezerSafe(40, 0));   // fp −22,24 ≤ −19,5
    ok(!SlushyPredictor::freezerSafe(20, 0));  // fp −9,76 > −19,5
});

it('Frío: estado físico a una temperatura dada', function () {
    eq('liquido', SlushyPredictor::stateAt(40, 0, -18));
    eq('solido', SlushyPredictor::stateAt(10, 0, -18));
});

// --- DilutionPlanner --------------------------------------------------------
it('Dilución: agua para bajar de 40 % a 20 %', function () {
    $d = DilutionPlanner::dilute(1000, 40, 20);
    ok($d['feasible']);
    near(1000.0, $d['water_ml'], 0.01);
    near(2000.0, $d['final_volume_ml'], 0.01);
});

it('Dilución: no se puede subir graduación con agua', function () {
    ok(!DilutionPlanner::dilute(1000, 20, 40)['feasible']);
});

it('Dilución: fortificar con destilado fuerte', function () {
    $f = DilutionPlanner::fortify(1000, 20, 30, 40);
    ok($f['feasible']);
    near(1000.0, $f['spirit_ml'], 0.01);
});

it('Dilución: cuadrado de Pearson 40/0 → 30', function () {
    $p = DilutionPlanner::pearson(40, 0, 30);
    near(0.75, $p['part_a'], 1e-4);
    near(0.25, $p['part_b'], 1e-4);
});

it('Dilución: graduación tras llevar a otro volumen', function () {
    near(20.0, DilutionPlanner::abvAfterDilution(1000, 40, 2000), 1e-9);
});

// --- AbvEstimator -----------------------------------------------------------
it('ABV: alcohol puro conservado y pérdida por evaporación', function () {
    near(400.0, AbvEstimator::alcoholMl([['volume_ml' => 1000, 'abv' => 40]]), 1e-9);
    near(368.0, AbvEstimator::alcoholMl([['volume_ml' => 1000, 'abv' => 40]], 0.08), 1e-9);
});

it('ABV: graduación = alcohol / volumen final', function () {
    near(22.169, AbvEstimator::abv(368, 1660), 0.001);
    near(0.0, AbvEstimator::abv(100, 0), 1e-9);
});

it('ABV: unidades estándar de 10 ml', function () {
    near(40.0, AbvEstimator::standardDrinks(400), 1e-9);
});