<?php
declare(strict_types=1);

namespace App\Calculation;

/**
 * Presets heredados del prototipo Taste Alchemy. El cliente recibe esta misma
 * tabla serializada para que el motor JS y el motor PHP no diverjan.
 */
final class RecipePresets
{
    public static function all(): array
    {
        return [
            'cereza' => [
                'label' => 'Licor de cereza ácida',
                'emoji' => '🍒',
                'fruit_name' => 'cerezas ácidas',
                'fruit_name_singular' => 'cereza ácida',
                'base_fruit_weight' => 500,
                'base_vodka' => 500,
                'citrus' => 'limón',
                'citrus_part' => 'piel',
                'spent_material' => 'cerezas gastadas',
                'extraction_note' => 'Las cerezas liberan un color granate profundo y una profundidad tánica y ácida en el alcohol.',
                'allows_berry_blend' => true,
                'default_sugar_ratio' => 0.40,
                'default_water_ratio' => 0.50,
            ],
            'hibisco' => [
                'label' => 'Licor de hibisco',
                'emoji' => '🌺',
                'fruit_name' => 'cálices de hibisco secos',
                'fruit_name_singular' => 'cáliz de hibisco seco',
                'base_fruit_weight' => 30,
                'base_vodka' => 500,
                'citrus' => 'naranja',
                'citrus_part' => 'piel',
                'spent_material' => 'cálices de hibisco gastados',
                'extraction_note' => 'Los cálices tiñen de carmesí intenso y aportan una acidez de arándano rojo.',
                'allows_berry_blend' => false,
                'default_sugar_ratio' => 0.40,
                'default_water_ratio' => 0.50,
            ],
            'frutos_rojos' => [
                'label' => 'Licor de frutos rojos',
                'emoji' => '🫐',
                'fruit_name' => 'frutos rojos',
                'fruit_name_singular' => 'fruto rojo',
                'base_fruit_weight' => 500,
                'base_vodka' => 500,
                'citrus' => 'limón',
                'citrus_part' => 'piel',
                'spent_material' => 'frutos rojos gastados',
                'extraction_note' => 'Los frutos rojos liberan un violeta profundo y una dulzura floral y confitada.',
                'allows_berry_blend' => false,
                'default_sugar_ratio' => 0.40,
                'default_water_ratio' => 0.50,
            ],
        ];
    }

    public static function get(string $code): array
    {
        return self::all()[$code] ?? self::all()['cereza'];
    }

    public static function extractions(): array
    {
        return [
            'hot' => [
                'label' => 'Extracción en caliente',
                'emoji' => '🔥',
                'days' => 1,
                'loss_pct' => 15.0,
                'summary' => 'Frascos sellados y baño maría suave, unas 2 horas. La extracción más rápida y potente, con alcohol perdido por evaporación que el cálculo compensa.',
                'steps' => 'Sella los frascos con el alcohol y el fruto y sumérgelos en un baño maría durante unas 2 horas, manteniendo el agua justo por debajo del hervor. Es el método más rápido y enérgico: parte del alcohol se evaporará y el motor lo compensa añadiendo vodka extra al principio.',
            ],
            'hybrid' => [
                'label' => 'Semana híbrida',
                'emoji' => '🌗',
                'days' => 3,
                'loss_pct' => 8.0,
                'summary' => '3 días de extracción en frío y un calor suave al final. Menos pérdida de alcohol y más aroma final.',
                'steps' => 'Deja los frascos a temperatura fresca, sin luz directa, durante 3 días completos. Al tercer día aplica un calor suave y breve para terminar la extracción. Conserva más alcohol que el método caliente y despierta más aroma.',
            ],
            'limoncello' => [
                'label' => 'Método limoncello',
                'emoji' => '❄️',
                'days' => 7,
                'loss_pct' => 0.0,
                'summary' => '7 días completamente en frío, agitando a diario. No se pierde alcohol en la extracción.',
                'steps' => 'Deja los frascos a temperatura fresca durante 7 días completos, agitando enérgicamente una vez al día para homogeneizar la extracción. Al no aplicar calor, no se pierde alcohol en esta fase.',
            ],
        ];
    }

    public static function syrups(): array
    {
        return [
            'homemade' => [
                'label' => 'Almíbar casero de limoncello',
                'emoji' => '🍯',
                'brix' => 65.0,
                'instructions' => [
                    'Calienta juntos el agua y el azúcar en un cazo.',
                    'Remueve hasta disolver completamente el azúcar.',
                    'Añade la piel del cítrico.',
                    'Incorpora el fruto gastado que quedó de la extracción.',
                    'Cuece a fuego suave 15–20 minutos, removiendo con regularidad.',
                    'Al colar, prensa el fruto para extraer el jugo y los aceites restantes.',
                    'Deja enfriar por completo antes de mezclar con el vodka infusionado.',
                ],
            ],
            'storebought' => [
                'label' => 'Almíbar de cereza comercial',
                'emoji' => '🧴',
                'brix' => 60.0,
                'instructions' => [
                    'Vierte el almíbar comercial en un cazo.',
                    'Añade el fruto gastado que quedó de la extracción.',
                    'Cuece 10–15 minutos removiendo de forma continua.',
                    'Al colar, prensa el fruto para extraer el jugo restante.',
                    'Retira la piel y los sólidos.',
                    'Deja enfriar completamente antes de incorporarlo al alcohol.',
                ],
            ],
        ];
    }
}
