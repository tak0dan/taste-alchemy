<?php
declare(strict_types=1);

namespace App\Calculation;

/**
 * Predicción del comportamiento en frío. La depresión del punto de congelación
 * se modela con una componente cuadrática en etanol (ajustada a datos
 * etanol-agua) y una componente lineal en azúcar:
 *
 *   fp(°C) = -(0.0034·ABV² + 0.42·ABV + 0.11·Brix)
 *
 * Referencias: 40 % v/v ≈ -22 °C, 20 % ≈ -10 °C, 10 % ≈ -4,5 °C.
 */
final class SlushyPredictor
{
    public static function freezingPointC(float $abv, float $brix): float
    {
        $abv = max(0.0, $abv);
        $brix = max(0.0, $brix);
        return -1.0 * (0.0034 * $abv * $abv + 0.42 * $abv + 0.11 * $brix);
    }

    /** @return 'liquido'|'slush'|'solido' */
    public static function stateAt(float $abv, float $brix, float $temperatureC = -18.0): string
    {
        $fp = self::freezingPointC($abv, $brix);
        if ($temperatureC <= $fp - 1.5) {
            return 'solido';
        }
        if ($temperatureC <= $fp + 0.5) {
            return 'slush';
        }
        return 'liquido';
    }

    /**
     * A -18 °C (congelador doméstico) el licor no debe solidificar. Exigimos
     * un margen de 1,5 °C sobre el punto de congelación.
     */
    public static function freezerSafe(float $abv, float $brix, float $freezerC = -18.0): bool
    {
        return self::freezingPointC($abv, $brix) <= $freezerC - 1.5;
    }

    public static function label(string $state): string
    {
        return match ($state) {
            'solido' => 'Se congela',
            'slush' => 'Textura de sorbete',
            default => 'Líquido',
        };
    }

    public static function servingTemperature(float $abv, float $brix): float
    {
        // Servicio recomendado unos 8 °C por encima del punto de congelación.
        return round(min(-4.0, self::freezingPointC($abv, $brix) + 8.0), 1);
    }
}
