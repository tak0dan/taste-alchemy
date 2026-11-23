<?php
declare(strict_types=1);

namespace App\Calculation;

/**
 * Graduación alcohólica. Modelo volumétrico conservativo: el alcohol puro no
 * se crea ni se destruye, cambia el volumen total (contracción etanol-agua
 * incluida como corrección opcional).
 */
final class AbvEstimator
{
    /** Contracción de mezcla etanol-agua: ~1.8 % a 40 % v/v. */
    public const CONTRACTION_AT_40 = 0.018;

    /**
     * @param array<int, array{volume_ml: float, abv: float}> $parts
     * @param float $lossFraction fracción de alcohol perdida por evaporación (0..1)
     */
    public static function alcoholMl(array $parts, float $lossFraction = 0.0): float
    {
        $lossFraction = max(0.0, min(1.0, $lossFraction));
        $alcohol = 0.0;
        foreach ($parts as $part) {
            $alcohol += (float) $part['volume_ml'] * ((float) $part['abv'] / 100.0);
        }
        return $alcohol * (1.0 - $lossFraction);
    }

    public static function abv(float $alcoholMl, float $finalVolumeMl): float
    {
        if ($finalVolumeMl <= 0.0) {
            return 0.0;
        }
        return ($alcoholMl / $finalVolumeMl) * 100.0;
    }

    /**
     * Corrección por contracción de volumen (opcional). Se usa en la
     * comprobación de equivalencia de las dos estrategias de cálculo.
     */
    public static function contraction(float $abv): float
    {
        $normalized = max(0.0, min(1.0, $abv / 40.0));
        return self::CONTRACTION_AT_40 * $normalized;
    }

    /** Volumen de alcohol puro expresado en unidades de 10 ml (para UI). */
    public static function standardDrinks(float $alcoholMl, float $unitMl = 10.0): float
    {
        return $unitMl <= 0 ? 0.0 : $alcoholMl / $unitMl;
    }
}
