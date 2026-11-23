<?php
declare(strict_types=1);

namespace App\Calculation;

/**
 * Relación azúcar ↔ Brix ↔ densidad. El Brix se mide en g de sacarosa por
 * 100 g de disolución; para pasar a g/L se usa la densidad aproximada
 * SG = 1 + 0.004 · Brix, suficientemente precisa para un licor.
 */
final class SugarModel
{
    public static function specificGravity(float $brix): float
    {
        return 1.0 + 0.004 * max(0.0, $brix);
    }

    /** g de azúcar por litro a partir de Brix. */
    public static function brixToSugarGl(float $brix): float
    {
        $brix = max(0.0, $brix);
        return $brix * 10.0 * self::specificGravity($brix);
    }

    /** Brix a partir de g/L (inversa iterativa). */
    public static function sugarGlToBrix(float $sugarGl): float
    {
        $sugarGl = max(0.0, $sugarGl);
        if ($sugarGl === 0.0) {
            return 0.0;
        }
        $brix = $sugarGl / 10.0;
        for ($i = 0; $i < 8; $i++) {
            $brix = $sugarGl / (10.0 * self::specificGravity($brix));
        }
        return $brix;
    }

    /** g de azúcar por 100 ml (etiqueta). */
    public static function sugarPer100Ml(float $sugarGl): float
    {
        return $sugarGl / 10.0;
    }

    /**
     * Alcohol potencial si todo el azúcar fermentase (referencia educativa;
     * en un licor el azúcar se añade después y no fermenta).
     */
    public static function potentialAbv(float $sugarGl): float
    {
        // 1 g de sacarosa → 0.511 g etanol; densidad etanol 0.789 g/ml.
        return ($sugarGl * 0.511 / 0.789) / 10.0;
    }

    public static function sweetnessClass(float $sugarGl): string
    {
        return match (true) {
            $sugarGl < 30 => 'seco',
            $sugarGl < 90 => 'semiseco',
            $sugarGl < 180 => 'dulce',
            default => 'muy dulce',
        };
    }

    /**
     * Volumen aparente que aporta el azúcar disuelto (mL). El prototipo usa
     * 0,15 mL por gramo; se conserva para no romper las recetas existentes.
     */
    public static function dissolvedVolumeMl(float $sugarG, float $factor = 0.15): float
    {
        return $sugarG * $factor;
    }
}
