<?php
declare(strict_types=1);

namespace App\Calculation;

/**
 * Cronograma de maceración y reposo. Devuelve hitos con días absolutos desde
 * el inicio, de forma que el cliente pueda pintarlos sin recalcular nada.
 */
final class TimelineBuilder
{
    public static function build(string $extractionCode, int $restDays = 14, int $startOffsetDays = 0): array
    {
        $extractions = RecipePresets::extractions();
        $extraction = $extractions[$extractionCode] ?? $extractions['limoncello'];
        $days = (int) $extraction['days'];
        $items = [];

        $items[] = [
            'at_day' => 1 + $startOffsetDays,
            'label' => 'Día 1',
            'title' => 'Preparar los frascos',
            'body' => 'Pesa el fruto, reparte en frascos de 250 g y cubre con el alcohol. Añade la piel de cítrico.',
        ];

        if ($days >= 7) {
            $items[] = [
                'at_day' => 2 + $startOffsetDays,
                'label' => 'Días 2–' . ($days - 1),
                'title' => 'Maceración en frío',
                'body' => 'Agita cada frasco una vez al día, siempre en un lugar fresco y sin luz directa.',
            ];
        } elseif ($days >= 3) {
            $items[] = [
                'at_day' => 2 + $startOffsetDays,
                'label' => 'Días 2–3',
                'title' => 'Maceración corta',
                'body' => 'Mantén los frascos en frío y sin luz. Al tercer día aplica un calor suave y breve.',
            ];
        }

        $items[] = [
            'at_day' => $days + $startOffsetDays,
            'label' => 'Día ' . $days,
            'title' => 'Colar y montar el almíbar',
            'body' => 'Cuela el alcohol infusionado, prensa el fruto y prepara el almíbar. Deja enfriar antes de mezclar.',
        ];

        $items[] = [
            'at_day' => $days + 1 + $startOffsetDays,
            'label' => 'Día ' . ($days + 1),
            'title' => 'Mezclar y embotellar',
            'body' => 'Incorpora el almíbar poco a poco, corrige dulzor y graduación, y embotella.',
        ];

        $items[] = [
            'at_day' => $days + 1 + $restDays + $startOffsetDays,
            'label' => 'Reposo',
            'title' => 'Reposar ' . $restDays . ' días',
            'body' => 'Reposa en frío y oscuridad, agitando suavemente cada pocos días. El licor mejora con el tiempo.',
        ];

        return [
            'extraction' => $extractionCode,
            'extraction_label' => $extraction['label'],
            'total_days' => $days + 1 + $restDays,
            'items' => $items,
        ];
    }
}
