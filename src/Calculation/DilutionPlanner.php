<?php
declare(strict_types=1);

namespace App\Calculation;

/**
 * Planificador de dilución. Resuelve las dos preguntas del taller:
 *   · ¿cuánta agua añado para bajar de A a B?
 *   · ¿cuánto destilado fuerte añado para subir de A a B?
 * Y el modo inverso: si fijo el volumen final, ¿qué graduación obtengo?
 */
final class DilutionPlanner
{
    /**
     * Agua necesaria para diluir hasta targetAbv.
     * @return array{water_ml: float, final_volume_ml: float, target_abv: float, feasible: bool}
     */
    public static function dilute(float $volumeMl, float $currentAbv, float $targetAbv): array
    {
        if ($targetAbv <= 0 || $currentAbv <= 0 || $volumeMl <= 0) {
            return ['water_ml' => 0.0, 'final_volume_ml' => $volumeMl, 'target_abv' => $targetAbv, 'feasible' => false];
        }
        if ($targetAbv > $currentAbv) {
            return ['water_ml' => 0.0, 'final_volume_ml' => $volumeMl, 'target_abv' => $targetAbv, 'feasible' => false];
        }
        $alcohol = $volumeMl * $currentAbv / 100.0;
        $finalVolume = $alcohol / ($targetAbv / 100.0);
        return [
            'water_ml' => round($finalVolume - $volumeMl, 2),
            'final_volume_ml' => round($finalVolume, 2),
            'target_abv' => round($targetAbv, 2),
            'feasible' => true,
        ];
    }

    /**
     * Destilado fuerte necesario para subir la graduación.
     * @return array{spirit_ml: float, final_volume_ml: float, final_abv: float, feasible: bool}
     */
    public static function fortify(float $volumeMl, float $currentAbv, float $targetAbv, float $spiritAbv): array
    {
        if ($volumeMl <= 0 || $targetAbv <= $currentAbv || $spiritAbv <= $targetAbv) {
            return ['spirit_ml' => 0.0, 'final_volume_ml' => $volumeMl, 'final_abv' => $currentAbv, 'feasible' => false];
        }
        $spiritMl = $volumeMl * ($targetAbv - $currentAbv) / ($spiritAbv - $targetAbv);
        return [
            'spirit_ml' => round($spiritMl, 2),
            'final_volume_ml' => round($volumeMl + $spiritMl, 2),
            'final_abv' => round($targetAbv, 2),
            'feasible' => true,
        ];
    }

    /**
     * Graduación resultante al llevar un volumen a otro mayor/menor.
     */
    public static function abvAfterDilution(float $volumeMl, float $currentAbv, float $finalVolumeMl): float
    {
        if ($finalVolumeMl <= 0) {
            return 0.0;
        }
        return ($volumeMl * $currentAbv) / $finalVolumeMl;
    }

    /**
     * Cuadrado de Pearson: proporción de dos componentes para una mezcla.
     * @return array{part_a: float, part_b: float}
     */
    public static function pearson(float $abvA, float $abvB, float $target): array
    {
        if ($target <= min($abvA, $abvB) || $target >= max($abvA, $abvB)) {
            return ['part_a' => 0.0, 'part_b' => 0.0];
        }
        $a = abs($target - $abvB);
        $b = abs($abvA - $target);
        $total = $a + $b;
        return $total == 0.0 ? ['part_a' => 0.0, 'part_b' => 0.0] : [
            'part_a' => round($a / $total, 4),
            'part_b' => round($b / $total, 4),
        ];
    }
}
