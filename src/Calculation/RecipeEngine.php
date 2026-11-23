<?php
declare(strict_types=1);

namespace App\Calculation;

/**
 * Motor de cálculo de Taste Alchemy.
 *
 * Es el mismo modelo que el prototipo de un solo archivo, extraído a una
 * biblioteca pura (sin base de datos, sesión ni red) para poder testearlo:
 *
 *   · modo «ingredientes»: fijo vodka y fruto, calculo el resultado;
 *   · modo «volumen deseado»: fijo el volumen final y retrocedo a los insumos;
 *   · ambos deben dar el mismo resultado con datos equivalentes.
 *
 * Constantes del prototipo conservadas: 0,40 g de azúcar por g de fruto,
 * 0,50 mL de agua por g de fruto, 0,18 mL de jugo por g, 0,15 mL de volumen
 * aparente por g de azúcar y 40 % v/v de vodka base.
 */
final class RecipeEngine
{
    public const SUGAR_RATIO = 0.40;
    public const WATER_RATIO = 0.50;
    public const JUICE_YIELD = 0.18;
    public const SUGAR_VOLUME_FACTOR = 0.15;
    public const VODKA_ABV = 40.0;
    public const JAR_FRUIT_G = 250.0;
    public const JAR_VOLUME_ML = 250.0;
    public const BOTTLE_ML = 700.0;

    public static function calculate(array $input = [], bool $withEquivalence = true): array
    {
        $presetCode = (string) ($input['base'] ?? 'cereza');
        $preset = RecipePresets::get($presetCode);
        $extractionCode = (string) ($input['extraction'] ?? 'limoncello');
        $extractions = RecipePresets::extractions();
        $extraction = $extractions[$extractionCode] ?? $extractions['limoncello'];
        $syrups = RecipePresets::syrups();
        $syrupCode = (string) ($input['syrup'] ?? 'homemade');
        $syrup = $syrups[$syrupCode] ?? $syrups['homemade'];

        $mode = ($input['mode'] ?? 'ingredientes') === 'volumen' ? 'volumen' : 'ingredientes';
        $baseAbv = self::number($input['base_abv_pct'] ?? self::VODKA_ABV, 0.0, 96.0);
        $lossPct = self::number($input['extraction_loss_pct'] ?? $extraction['loss_pct'], 0.0, 50.0);
        $sugarRatio = self::number($input['sugar_ratio'] ?? $preset['default_sugar_ratio'], 0.0, 2.0);
        $waterRatio = self::number($input['water_ratio'] ?? $preset['default_water_ratio'], 0.0, 3.0);
        $juiceYield = self::number($input['juice_yield'] ?? self::JUICE_YIELD, 0.0, 1.0);
        $sugarVolumeFactor = self::number($input['sugar_volume_factor'] ?? self::SUGAR_VOLUME_FACTOR, 0.0, 1.0);
        $restDays = (int) self::number($input['rest_days'] ?? 14, 0, 365);
        $blendAllowed = (bool) $preset['allows_berry_blend'];
        $blendEnabled = (bool) ($input['blend_enabled'] ?? false) && $blendAllowed;
        $blendPct = self::number($input['blend_pct'] ?? 15, 0, 90);
        $fruitToVodka = self::number(
            $input['fruit_to_vodka_ratio'] ?? ($preset['base_fruit_weight'] / $preset['base_vodka']),
            0.0001,
            10.0
        );

        $vodkaMl = self::number($input['vodka_ml'] ?? 500, 0.0, 100000.0);
        $fruitG = self::number($input['ingredient_g'] ?? 500, 0.0, 100000.0);
        $desiredVolume = self::number($input['desired_volume_ml'] ?? 1000, 1.0, 1000000.0);

        if ($mode === 'volumen') {
            [$vodkaMl, $fruitG] = self::solveFromVolume(
                $desiredVolume,
                $fruitToVodka,
                $sugarRatio,
                $waterRatio,
                $juiceYield,
                $sugarVolumeFactor,
                $lossPct
            );
        }

        $loss = $lossPct / 100.0;
        $cherriesG = $fruitG;
        $berriesG = 0.0;
        if ($blendEnabled) {
            $berriesG = round($fruitG * ($blendPct / 100.0), 2);
            $cherriesG = round($fruitG - $berriesG, 2);
        }

        $sugarG = isset($input['sugar_g']) && $input['sugar_g'] !== ''
            ? self::number($input['sugar_g'], 0.0, 100000.0)
            : round($fruitG * $sugarRatio, 2);
        $waterMl = isset($input['water_ml']) && $input['water_ml'] !== ''
            ? self::number($input['water_ml'], 0.0, 100000.0)
            : round($fruitG * $waterRatio, 2);

        $netVodkaMl = $vodkaMl * (1.0 - $loss);
        $juiceMl = $fruitG * $juiceYield;
        $sugarVolumeMl = SugarModel::dissolvedVolumeMl($sugarG, $sugarVolumeFactor);
        $finalVolumeMl = $netVodkaMl + $waterMl + $juiceMl + $sugarVolumeMl;

        $alcoholMl = $vodkaMl * ($baseAbv / 100.0) * (1.0 - $loss);
        $abv = AbvEstimator::abv($alcoholMl, $finalVolumeMl);
        $sugarGl = $finalVolumeMl > 0 ? ($sugarG / ($finalVolumeMl / 1000.0)) : 0.0;
        $brix = SugarModel::sugarGlToBrix($sugarGl);
        $freezePoint = SlushyPredictor::freezingPointC($abv, $brix);
        $freezerState = SlushyPredictor::stateAt($abv, $brix, -18.0);

        $timeline = TimelineBuilder::build($extractionCode, $restDays);

        $warnings = [];
        if ($finalVolumeMl <= 0) {
            $warnings[] = 'El volumen final es cero: revisa las cantidades.';
        }
        if ($abv < 15.0) {
            $warnings[] = 'Graduación por debajo del 15 %: no es estable como licor de guarda.';
        }
        if ($abv > 45.0) {
            $warnings[] = 'Graduación por encima del 45 %: diluye o reduce el alcohol de base.';
        }
        if ($sugarGl > 300.0) {
            $warnings[] = 'Más de 300 g/L de azúcar: el almíbar puede enmascarar el fruto.';
        }
        if (!self::freezerStateSafe($freezerState)) {
            $warnings[] = 'A −18 °C no hay margen de congelador; sirve muy frío pero no lo congeles.';
        }
        if (($input['base'] ?? null) && !isset(RecipePresets::all()[$presetCode])) {
            $warnings[] = 'Base no reconocida: se ha usado la receta de cereza por defecto.';
        }

        $result = [
            'mode' => $mode,
            'preset' => [
                'code' => $presetCode,
                'label' => $preset['label'],
                'emoji' => $preset['emoji'],
                'fruit_name' => $preset['fruit_name'],
                'fruit_name_singular' => $preset['fruit_name_singular'],
                'allows_berry_blend' => $blendAllowed,
                'citrus' => $preset['citrus'],
                'citrus_part' => $preset['citrus_part'],
                'extraction_note' => $preset['extraction_note'],
            ],
            'extraction' => [
                'code' => $extractionCode,
                'label' => $extraction['label'],
                'days' => (int) $extraction['days'],
                'loss_pct' => $lossPct,
                'summary' => $extraction['summary'],
                'steps' => $extraction['steps'],
            ],
            'syrup' => [
                'code' => $syrupCode,
                'label' => $syrup['label'],
                'brix' => (float) $syrup['brix'],
                'instructions' => $syrup['instructions'],
            ],
            'input' => [
                'base' => $presetCode,
                'mode' => $mode,
                'base_abv_pct' => round($baseAbv, 2),
                'vodka_ml' => round($vodkaMl, 2),
                'ingredient_g' => round($fruitG, 2),
                'desired_volume_ml' => round($desiredVolume, 2),
                'extraction' => $extractionCode,
                'extraction_loss_pct' => round($lossPct, 2),
                'sugar_ratio' => round($sugarRatio, 3),
                'water_ratio' => round($waterRatio, 3),
                'juice_yield' => round($juiceYield, 3),
                'sugar_volume_factor' => round($sugarVolumeFactor, 3),
                'blend_enabled' => $blendEnabled,
                'blend_pct' => round($blendPct, 2),
                'rest_days' => $restDays,
                'syrup' => $syrupCode,
                'flavor' => (string) ($input['flavor'] ?? 'original'),
                'custom_spice' => (string) ($input['custom_spice'] ?? ''),
                'split' => (bool) ($input['split'] ?? false),
            ],
            'ingredients' => [
                'vodka_ml' => round($vodkaMl, 1),
                'fruit_g' => round($fruitG, 1),
                'cherries_g' => round($cherriesG, 1),
                'berries_g' => round($berriesG, 1),
                'sugar_g' => round($sugarG, 1),
                'water_ml' => round($waterMl, 1),
                'citrus' => $preset['citrus'],
                'citrus_part' => $preset['citrus_part'],
            ],
            'volumes' => [
                'net_vodka_ml' => round($netVodkaMl, 2),
                'juice_ml' => round($juiceMl, 2),
                'sugar_volume_ml' => round($sugarVolumeMl, 2),
                'water_ml' => round($waterMl, 2),
                'final_ml' => round($finalVolumeMl, 2),
                'final_l' => round($finalVolumeMl / 1000.0, 3),
            ],
            'abv_pct' => round($abv, 3),
            'alcohol_ml' => round($alcoholMl, 2),
            'sugar_gl' => round($sugarGl, 2),
            'brix' => round($brix, 2),
            'sugar_per_100ml' => round(SugarModel::sugarPer100Ml($sugarGl), 2),
            'sweetness' => SugarModel::sweetnessClass($sugarGl),
            'freeze' => [
                'freezing_point_c' => round($freezePoint, 2),
                'state_at_minus18' => $freezerState,
                'freezer_safe' => SlushyPredictor::freezerSafe($abv, $brix, -18.0),
                'label' => SlushyPredictor::label($freezerState),
                'serving_temperature_c' => SlushyPredictor::servingTemperature($abv, $brix),
            ],
            'jars' => [
                'fruit' => self::packFruitJars($cherriesG, $berriesG),
                'vodka' => self::packVolumeJars($vodkaMl),
            ],
            'bottles_700_ml' => round($finalVolumeMl / self::BOTTLE_ML, 2),
            'timeline' => $timeline,
            'dilution' => [
                'to_30' => DilutionPlanner::dilute($finalVolumeMl, $abv, 30.0),
                'to_28' => DilutionPlanner::dilute($finalVolumeMl, $abv, 28.0),
                'pearson_40_to_0_at_30' => DilutionPlanner::pearson(40.0, 0.0, 30.0),
            ],
            'warnings' => $warnings,
            'computed_at' => date('c'),
        ];

        if ($withEquivalence) {
            $equivalentInput = [
                'base' => $presetCode,
                'mode' => 'ingredientes',
                'base_abv_pct' => $baseAbv,
                'vodka_ml' => round($vodkaMl, 2),
                'ingredient_g' => round($fruitG, 2),
                'extraction' => $extractionCode,
                'extraction_loss_pct' => $lossPct,
                'sugar_ratio' => $sugarRatio,
                'water_ratio' => $waterRatio,
                'juice_yield' => $juiceYield,
                'sugar_volume_factor' => $sugarVolumeFactor,
                'blend_enabled' => $blendEnabled,
                'blend_pct' => $blendPct,
                'rest_days' => $restDays,
                'syrup' => $syrupCode,
                'sugar_g' => $sugarG,
                'water_ml' => $waterMl,
            ];
            $equivalent = self::calculate($equivalentInput, false);
            $result['equivalents'] = [
                'mode' => 'ingredientes',
                'abv_pct' => $equivalent['abv_pct'],
                'final_ml' => $equivalent['volumes']['final_ml'],
                'delta_abv' => round(abs($equivalent['abv_pct'] - $result['abv_pct']), 4),
                'delta_volume_ml' => round(abs($equivalent['volumes']['final_ml'] - $result['volumes']['final_ml']), 4),
            ];
        }

        return $result;
    }

    /**
     * Escala una receta completa sin alterar su equilibrio.
     */
    public static function scale(array $input, float $factor): array
    {
        $factor = max(0.001, min(1000.0, $factor));
        if (($input['mode'] ?? 'ingredientes') === 'volumen') {
            $input['desired_volume_ml'] = ((float) ($input['desired_volume_ml'] ?? 1000)) * $factor;
        } else {
            $input['vodka_ml'] = ((float) ($input['vodka_ml'] ?? 500)) * $factor;
            $input['ingredient_g'] = ((float) ($input['ingredient_g'] ?? 500)) * $factor;
        }
        return self::calculate($input);
    }

    /**
     * @return array{0: float, 1: float} vodka mL, fruto g
     */
    private static function solveFromVolume(
        float $desiredVolume,
        float $fruitToVodka,
        float $sugarRatio,
        float $waterRatio,
        float $juiceYield,
        float $sugarVolumeFactor,
        float $lossPct
    ): array {
        $loss = $lossPct / 100.0;
        $refVodka = 1000.0;
        $refFruit = $refVodka * $fruitToVodka;
        $refSugar = $refFruit * $sugarRatio;
        $refWater = $refFruit * $waterRatio;
        $refNetVodka = $refVodka * (1.0 - $loss);
        $refJuice = $refFruit * $juiceYield;
        $refFinal = $refNetVodka + $refWater + $refJuice + ($refSugar * $sugarVolumeFactor);
        $yieldFactor = $refFinal / $refVodka;
        $vodka = $desiredVolume / $yieldFactor;
        $fruit = $vodka * $fruitToVodka;
        return [round($vodka, 2), round($fruit, 2)];
    }

    /**
     * @return array<int, array<string, float|int|string>>
     */
    private static function packFruitJars(float $cherriesG, float $berriesG): array
    {
        $jars = [];
        $capacity = self::JAR_FRUIT_G;
        $cherries = $cherriesG;
        $berries = $berriesG;

        while ($cherries >= $capacity) {
            $jars[] = ['cherries_g' => $capacity, 'berries_g' => 0.0, 'total_g' => $capacity];
            $cherries -= $capacity;
        }
        if ($cherries > 0) {
            $space = $capacity - $cherries;
            $berriesHere = min($space, $berries);
            $jars[] = ['cherries_g' => round($cherries, 1), 'berries_g' => round($berriesHere, 1), 'total_g' => round($cherries + $berriesHere, 1)];
            $berries -= $berriesHere;
            $cherries = 0;
        }
        while ($berries > 0) {
            $chunk = min($capacity, $berries);
            $jars[] = ['cherries_g' => 0.0, 'berries_g' => round($chunk, 1), 'total_g' => round($chunk, 1)];
            $berries -= $chunk;
        }
        if ($jars === []) {
            $jars[] = ['cherries_g' => 0.0, 'berries_g' => 0.0, 'total_g' => 0.0];
        }
        foreach ($jars as &$jar) {
            $jar['fill_pct'] = min(100, round(($jar['total_g'] / $capacity) * 100, 1));
        }
        return $jars;
    }

    /**
     * @return array<int, array{ml: float, fill_pct: float}>
     */
    private static function packVolumeJars(float $totalMl): array
    {
        $jars = [];
        $remaining = $totalMl;
        while ($remaining > 0.0001) {
            $chunk = min(self::JAR_VOLUME_ML, $remaining);
            $jars[] = ['ml' => round($chunk, 1), 'fill_pct' => min(100, round(($chunk / self::JAR_VOLUME_ML) * 100, 1))];
            $remaining -= $chunk;
        }
        if ($jars === []) {
            $jars[] = ['ml' => 0.0, 'fill_pct' => 0.0];
        }
        return $jars;
    }

    private static function freezerStateSafe(string $state): bool
    {
        return $state === 'liquido';
    }

    private static function number(mixed $value, float $min, float $max): float
    {
        if (!is_numeric($value)) {
            return $min > 0 ? $min : 0.0;
        }
        return max($min, min($max, (float) $value));
    }
}
