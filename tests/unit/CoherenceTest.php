<?php
declare(strict_types=1);

use App\Calculation\CoherenceCalculator;

/**
 * Genera lecturas que siguen exactamente la física del veredicto:
 * evaporación 2,5 %/año y pérdida de graduación 0,35 puntos/año.
 */
function coherent_readings(string $start, array $days, float $v0, float $abv0): array
{
    $t0 = strtotime($start);
    $out = [];
    foreach ($days as $day) {
        $years = $day / 365.25;
        $out[] = [
            'taken_at' => date('Y-m-d', $t0 + $day * 86400),
            'volume_l' => round($v0 * (1 - 0.025 * $years), 3),
            'abv' => round($abv0 - 0.35 * $years, 2),
        ];
    }
    return $out;
}

it('Coherencia: lote bien medido puntúa alto y es sólido', function () {
    $readings = coherent_readings('2025-01-01', [0, 90, 180, 270, 360], 20.0, 24.0);
    $last = $readings[count($readings) - 1];
    $result = CoherenceCalculator::compute([
        'id' => 1, 'initial_volume_l' => 20.0, 'initial_abv' => 24.0,
        'declared_abv' => $last['abv'], 'started_at' => '2025-01-01',
    ], $readings);
    ok($result['score'] >= 85, 'esperaba ≥85, obtuvo ' . $result['score']);
    eq('solido', $result['verdict']);
    eq(4, count($result['checks']));
});

it('Coherencia: sin lecturas no hay evidencia (score 0)', function () {
    $result = CoherenceCalculator::compute([
        'id' => 2, 'initial_volume_l' => 10.0, 'initial_abv' => 26.0,
        'declared_abv' => 26.0, 'started_at' => '2025-01-01',
    ], []);
    eq(0, $result['score']);
    eq('sin_datos', $result['verdict']);
});

it('Coherencia: un repunte anómalo de ABV resta puntuación', function () {
    $good = coherent_readings('2025-01-01', [0, 90, 180, 270, 360], 20.0, 24.0);
    $bad = $good;
    $bad[2]['abv'] = $bad[1]['abv'] + 3.0; // repunte imposible
    $lot = [
        'id' => 3, 'initial_volume_l' => 20.0, 'initial_abv' => 24.0,
        'declared_abv' => $good[4]['abv'], 'started_at' => '2025-01-01',
    ];
    $scoreGood = CoherenceCalculator::compute($lot, $good)['score'];
    $scoreBad = CoherenceCalculator::compute($lot, $bad)['score'];
    ok($scoreBad < $scoreGood, "el repunte debería restar: {$scoreBad} < {$scoreGood}");
});

it('Coherencia: declarar un ABV lejano a la última lectura penaliza', function () {
    $readings = coherent_readings('2025-01-01', [0, 90, 180, 270, 360], 20.0, 24.0);
    $last = $readings[4]['abv'];
    $honest = CoherenceCalculator::compute([
        'id' => 4, 'initial_volume_l' => 20.0, 'initial_abv' => 24.0,
        'declared_abv' => $last, 'started_at' => '2025-01-01',
    ], $readings)['score'];
    $liar = CoherenceCalculator::compute([
        'id' => 4, 'initial_volume_l' => 20.0, 'initial_abv' => 24.0,
        'declared_abv' => $last + 6.0, 'started_at' => '2025-01-01',
    ], $readings)['score'];
    ok($liar < $honest, "declarar de más debería penalizar: {$liar} < {$honest}");
});

it('Coherencia: los cuatro pesos suman 1', function () {
    $w = CoherenceCalculator::DEFAULTS;
    near(1.0, $w['weight_evaporation'] + $w['weight_abv'] + $w['weight_cadence'] + $w['weight_declaration'], 1e-9);
});