<?php
declare(strict_types=1);

namespace App\Calculation;

/**
 * Réplica en PHP puro del procedimiento sp_coherencia_lote. Sirve para dos
 * cosas: testear la física sin base de datos y comprobar que el procedimiento
 * y el código dan exactamente el mismo veredicto (prueba de integración).
 */
final class CoherenceCalculator
{
    public const DEFAULTS = [
        'evaporation_k' => 0.025,
        'abv_k' => 0.35,
        'evap_tolerance' => 0.15,
        'abv_tolerance' => 4.0,
        'declaration_tolerance' => 0.5,
        'cadence_per_year' => 4.0,
        'weight_evaporation' => 0.35,
        'weight_abv' => 0.25,
        'weight_cadence' => 0.20,
        'weight_declaration' => 0.20,
    ];

    /**
     * @param array{id?:int, initial_volume_l:float, initial_abv:float, declared_abv:float, started_at:string} $lot
     * @param array<int, array{taken_at:string, volume_l:float, abv:float}> $readings
     */
    public static function compute(array $lot, array $readings, array $settings = []): array
    {
        $s = array_merge(self::DEFAULTS, $settings);
        $lotId = (int) ($lot['id'] ?? 0);
        $initialVolume = (float) $lot['initial_volume_l'];
        $initialAbv = (float) $lot['initial_abv'];
        $declaredAbv = (float) $lot['declared_abv'];
        $startedAt = new \DateTimeImmutable($lot['started_at']);

        $readings = array_values(array_filter($readings, static fn($r) => isset($r['taken_at'], $r['volume_l'], $r['abv'])));
        usort($readings, static function (array $a, array $b): int {
            return [$a['taken_at'], $a['volume_l']] <=> [$b['taken_at'], $b['volume_l']];
        });

        if ($readings === []) {
            return [
                'lot_id' => $lotId,
                'score' => 0,
                'computed_at' => date('c'),
                'verdict' => 'sin_datos',
                'checks' => [],
                'explanation' => 'El lote no tiene lecturas: no hay evidencia física que verificar.',
            ];
        }

        $last = $readings[count($readings) - 1];
        $firstDate = new \DateTimeImmutable($readings[0]['taken_at']);
        $lastDate = new \DateTimeImmutable($last['taken_at']);
        $spanDays = max(0, (int) $firstDate->diff($lastDate)->format('%r%a'));
        $ageDays = (float) $startedAt->diff($lastDate)->format('%r%a');
        $years = max(0.0, $ageDays / 365.25);

        // A) Evaporación
        $expectedVolume = $initialVolume * (1.0 - $s['evaporation_k'] * $years);
        if ($expectedVolume <= 0) {
            $expectedVolume = 0.001;
        }
        $evapDev = abs((float) $last['volume_l'] - $expectedVolume) / $expectedVolume;
        $evapScore = self::score(1.0 - $evapDev / max(0.00001, $s['evap_tolerance']));

        // B) Trayectoria de graduación
        $expectedAbv = max(0.0, $initialAbv - $s['abv_k'] * $years);
        $abvDev = abs((float) $last['abv'] - $expectedAbv);
        $abvScore = self::score(1.0 - $abvDev / max(0.00001, $s['abv_tolerance']));

        $increases = 0;
        for ($i = 1; $i < count($readings); $i++) {
            if ((float) $readings[$i]['abv'] > (float) $readings[$i - 1]['abv'] + 0.5) {
                $increases++;
            }
        }
        if ($increases > 0) {
            $abvScore = max(0.0, $abvScore - min(30.0, $increases * 5.0));
        }

        // C) Cadencia
        $maxGap = 0;
        for ($i = 1; $i < count($readings); $i++) {
            $prev = new \DateTimeImmutable($readings[$i - 1]['taken_at']);
            $curr = new \DateTimeImmutable($readings[$i]['taken_at']);
            $maxGap = max($maxGap, (int) $prev->diff($curr)->format('%r%a'));
        }
        $expectedReadings = max(1, (int) ceil($years * $s['cadence_per_year']));
        $countFactor = min(1.0, count($readings) / $expectedReadings);
        $gapFactor = $spanDays <= 0 ? 0.5 : max(0.0, 1.0 - ($maxGap / ($spanDays + 1)));
        $cadenceScore = round(100.0 * (0.6 * $countFactor + 0.4 * $gapFactor), 2);

        // D) Declaración
        $declDev = abs($declaredAbv - (float) $last['abv']);
        $declScore = self::score(1.0 - $declDev / max(0.00001, $s['declaration_tolerance']));

        $total = (int) round(
            $s['weight_evaporation'] * $evapScore +
            $s['weight_abv'] * $abvScore +
            $s['weight_cadence'] * $cadenceScore +
            $s['weight_declaration'] * $declScore
        );

        return [
            'lot_id' => $lotId,
            'score' => $total,
            'computed_at' => date('c'),
            'verdict' => self::verdict($total),
            'physics' => [
                'years' => round($years, 3),
                'initial_volume_l' => $initialVolume,
                'latest_volume_l' => (float) $last['volume_l'],
                'expected_volume_l' => round($expectedVolume, 3),
                'initial_abv' => $initialAbv,
                'latest_abv' => (float) $last['abv'],
                'expected_abv' => round($expectedAbv, 2),
                'declared_abv' => $declaredAbv,
                'reading_count' => count($readings),
                'first_reading' => $readings[0]['taken_at'],
                'last_reading' => $last['taken_at'],
                'span_days' => $spanDays,
                'max_gap_days' => $maxGap,
            ],
            'checks' => [
                [
                    'code' => 'evaporation',
                    'label' => 'Evaporación de barrica',
                    'score' => (int) round($evapScore),
                    'weight' => $s['weight_evaporation'],
                    'expected' => round($expectedVolume, 2),
                    'actual' => (float) $last['volume_l'],
                    'deviation' => round($evapDev * 100, 2),
                    'unit' => '% volumen',
                    'status' => self::checkStatus($evapScore),
                    'explanation' => sprintf(
                        'La pérdida real es %.2f %%, frente al %.2f %% anual esperado durante %.2f años.',
                        $evapDev * 100,
                        $s['evaporation_k'] * 100,
                        $years
                    ),
                ],
                [
                    'code' => 'abv',
                    'label' => 'Trayectoria de graduación',
                    'score' => (int) round($abvScore),
                    'weight' => $s['weight_abv'],
                    'expected' => round($expectedAbv, 2),
                    'actual' => (float) $last['abv'],
                    'deviation' => round($abvDev, 2),
                    'unit' => 'puntos ABV',
                    'status' => self::checkStatus($abvScore),
                    'explanation' => sprintf(
                        'La graduación medida se desvía %.2f puntos de la trayectoria esperada%s',
                        $abvDev,
                        $increases > 0 ? "; además hay {$increases} repuntes anómalos." : '.'
                    ),
                ],
                [
                    'code' => 'cadence',
                    'label' => 'Cadencia de lecturas',
                    'score' => (int) round($cadenceScore),
                    'weight' => $s['weight_cadence'],
                    'expected' => $expectedReadings,
                    'actual' => count($readings),
                    'deviation' => $maxGap,
                    'unit' => 'lecturas / hueco máximo en días',
                    'status' => self::checkStatus($cadenceScore),
                    'explanation' => sprintf(
                        '%d lecturas repartidas en %d días; hueco máximo de %d días. Se esperaban al menos %d lecturas.',
                        count($readings),
                        $spanDays,
                        $maxGap,
                        $expectedReadings
                    ),
                ],
                [
                    'code' => 'declaration',
                    'label' => 'Declaración frente a última lectura',
                    'score' => (int) round($declScore),
                    'weight' => $s['weight_declaration'],
                    'expected' => (float) $last['abv'],
                    'actual' => $declaredAbv,
                    'deviation' => round($declDev, 2),
                    'unit' => 'puntos ABV',
                    'status' => self::checkStatus($declScore),
                    'explanation' => sprintf(
                        'El ABV declarado (%.2f %%) y el de la última lectura (%.2f %%) difieren %.2f puntos.',
                        $declaredAbv,
                        (float) $last['abv'],
                        $declDev
                    ),
                ],
            ],
            'explanation' => sprintf(
                'Coherencia %d/100: %d lecturas durante %.2f años. La evidencia física es la única credencial que no se puede fabricar.',
                $total,
                count($readings),
                $years
            ),
        ];
    }

    private static function score(float $factor): float
    {
        return max(0.0, min(100.0, round(100.0 * $factor, 2)));
    }

    private static function checkStatus(float $score): string
    {
        return $score >= 70 ? 'ok' : ($score >= 40 ? 'warn' : 'bad');
    }

    private static function verdict(int $score): string
    {
        if ($score >= 85) {
            return 'solido';
        }
        if ($score >= 70) {
            return 'plausible';
        }
        if ($score >= 45) {
            return 'dudoso';
        }
        return 'incoherente';
    }
}
