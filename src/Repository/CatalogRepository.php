<?php
declare(strict_types=1);

namespace App\Repository;

use App\Calculation\CoherenceCalculator;
use App\Core\Database;

/**
 * Catálogos administrables e ingredientes enriquecidos desde la API pública
 * de alimentos. Los umbrales de confianza viven en `settings`.
 */
final class CatalogRepository
{
    /** @return array<int, array<string, mixed>> */
    public function ingredients(bool $onlyActive = false): array
    {
        $where = $onlyActive ? 'WHERE active = 1' : '';
        return Database::select("SELECT * FROM ingredients {$where} ORDER BY category, name");
    }

    /** @return array<int, array<string, mixed>> */
    public function ingredientsByCategory(): array
    {
        $rows = $this->ingredients(true);
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['category']][] = $row;
        }
        return $grouped;
    }

    public function ingredient(int $id): ?array
    {
        return Database::first('SELECT * FROM ingredients WHERE id = ?', [$id]);
    }

    public function createIngredient(array $data): int
    {
        return Database::insert(
            'INSERT INTO ingredients (name, slug, category, brix, sugar_g_per_100, water_pct, abv_pct, source, external_ref, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (string) $data['name'],
                (string) $data['slug'],
                (string) $data['category'],
                $data['brix'] ?? null,
                $data['sugar_g_per_100'] ?? null,
                $data['water_pct'] ?? null,
                $data['abv_pct'] ?? null,
                $data['source'] ?? 'manual',
                $data['external_ref'] ?? null,
                $data['notes'] ?? null,
            ]
        );
    }

    public function updateIngredient(int $id, array $data): void
    {
        Database::execute(
            'UPDATE ingredients SET name = ?, category = ?, brix = ?, sugar_g_per_100 = ?, water_pct = ?, abv_pct = ?, notes = ?, active = ? WHERE id = ?',
            [
                (string) $data['name'],
                (string) $data['category'],
                $data['brix'] ?? null,
                $data['sugar_g_per_100'] ?? null,
                $data['water_pct'] ?? null,
                $data['abv_pct'] ?? null,
                $data['notes'] ?? null,
                !empty($data['active']) ? 1 : 0,
                $id,
            ]
        );
    }

    public function ingredientBySlug(string $slug): ?array
    {
        return Database::first('SELECT * FROM ingredients WHERE slug = ?', [$slug]);
    }

    /** @return array<int, array<string, mixed>> */
    public function extractions(): array
    {
        return Database::select('SELECT * FROM extraction_methods WHERE active = 1 ORDER BY days');
    }

    /** @return array<int, array<string, mixed>> */
    public function syrups(): array
    {
        return Database::select('SELECT * FROM syrups WHERE active = 1 ORDER BY name');
    }

    /** @return array<string, string> */
    public function settings(): array
    {
        $rows = Database::select('SELECT setting_key, setting_value FROM settings ORDER BY setting_key');
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
        return $out;
    }

    public function setting(string $key, ?string $default = null): ?string
    {
        $value = Database::value('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
        return $value === null ? $default : (string) $value;
    }

    public function setSetting(string $key, string $value, ?int $updatedBy = null): void
    {
        Database::execute(
            'INSERT INTO settings (setting_key, setting_value, label, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
            [$key, $value, str_replace(['.', '_'], ' ', ucfirst($key)), $updatedBy]
        );
    }

    /** @return array<string, float> */
    public function coherenceSettings(): array
    {
        $settings = $this->settings();
        $map = [
            'evaporation_k' => 'coherence.evaporation_k',
            'abv_k' => 'coherence.abv_k',
            'evap_tolerance' => 'coherence.evap_tolerance',
            'abv_tolerance' => 'coherence.abv_tolerance',
            'declaration_tolerance' => 'coherence.declaration_tolerance',
            'cadence_per_year' => 'coherence.cadence_per_year',
            'weight_evaporation' => 'coherence.weight_evaporation',
            'weight_abv' => 'coherence.weight_abv',
            'weight_cadence' => 'coherence.weight_cadence',
            'weight_declaration' => 'coherence.weight_declaration',
        ];
        $out = CoherenceCalculator::DEFAULTS;
        foreach ($map as $key => $settingKey) {
            if (isset($settings[$settingKey]) && is_numeric($settings[$settingKey])) {
                $out[$key] = (float) $settings[$settingKey];
            }
        }
        return $out;
    }

    public function threshold(string $key, float $default): float
    {
        $value = $this->setting($key);
        return $value !== null && is_numeric($value) ? (float) $value : $default;
    }
}
