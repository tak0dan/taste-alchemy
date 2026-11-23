<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repository\AuditRepository;
use App\Repository\CatalogRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;

final class AdminService
{
    private CatalogRepository $catalog;
    private UserRepository $users;
    private ProfileRepository $profiles;
    private AuditRepository $audit;

    private const NUMERIC_SETTINGS = [
        'coherence.weight_evaporation', 'coherence.weight_abv', 'coherence.weight_cadence',
        'coherence.weight_declaration', 'coherence.evaporation_k', 'coherence.abv_k',
        'coherence.evap_tolerance', 'coherence.abv_tolerance', 'coherence.cadence_per_year',
        'coherence.declaration_tolerance', 'trust.listing_min_score', 'trust.review_reflection_hours',
        'trust.min_lot_age_days', 'billing.vat_rate', 'consignment.default_days',
    ];

    public function __construct()
    {
        $this->catalog = new CatalogRepository();
        $this->users = new UserRepository();
        $this->profiles = new ProfileRepository();
        $this->audit = new AuditRepository();
    }

    public function metrics(): array
    {
        $result = Database::call('sp_metricas_plataforma', [], ['json']);
        return json_data($result['json'] ?? null);
    }

    public function dashboard(): array
    {
        return [
            'metrics' => $this->metrics(),
            'users' => $this->users->allWithRoles(),
            'artisans' => $this->profiles->allArtisans(),
            'venues' => $this->profiles->allVenues(),
            'settings' => $this->catalog->settings(),
            'ingredients' => $this->catalog->ingredients(),
            'extractions' => $this->catalog->extractions(),
            'syrups' => $this->catalog->syrups(),
        ];
    }

    public function updateSetting(int $adminId, string $key, string $value): void
    {
        $known = $this->catalog->settings();
        if (!array_key_exists($key, $known)) {
            throw new ValidationException(['setting' => 'Ese parámetro no existe.']);
        }
        if (in_array($key, self::NUMERIC_SETTINGS, true) && !is_numeric($value)) {
            throw new ValidationException(['value' => 'Ese parámetro debe ser numérico.']);
        }
        if ($key === 'coherence.weight_evaporation') {
            $weights = ['coherence.weight_evaporation', 'coherence.weight_abv', 'coherence.weight_cadence', 'coherence.weight_declaration'];
            $sum = 0.0;
            foreach ($weights as $weightKey) {
                $sum += $weightKey === $key ? (float) $value : (float) ($known[$weightKey] ?? 0);
            }
            if (abs($sum - 1.0) > 0.001) {
                throw new ValidationException(['value' => 'Los cuatro pesos de coherencia deben sumar 1,00.']);
            }
        }
        $this->catalog->setSetting($key, $value, $adminId);
        $this->audit->log('settings.updated', 'setting', null, $adminId, [
            'key' => $key,
            'old' => $known[$key] ?? null,
            'new' => $value,
        ]);
    }

    public function updateRoles(int $adminId, int $userId, array $roleCodes): void
    {
        $allowed = ['artesano', 'local', 'comisario', 'moderador', 'admin'];
        $roleCodes = array_values(array_intersect($allowed, $roleCodes));
        if ((int) $adminId === $userId && !in_array('admin', $roleCodes, true)) {
            throw new HttpException(422, 'No puedes quitarte a ti mismo el rol de administrador.');
        }
        $user = $this->users->find($userId);
        if ($user === null) {
            throw HttpException::notFound('El usuario no existe.');
        }
        $before = $user['roles'];
        Database::transaction(function () use ($userId, $roleCodes, $adminId): void {
            foreach (['artesano', 'local', 'comisario', 'moderador', 'admin'] as $code) {
                $this->users->removeRole($userId, $code);
            }
            foreach ($roleCodes as $code) {
                $this->users->addRole($userId, $code, $adminId);
            }
        });
        $this->audit->log('user.roles', 'user', $userId, $adminId, [
            'before' => $before,
            'after' => $roleCodes,
        ]);
    }

    public function createIngredient(int $adminId, array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['name' => 'El nombre es obligatorio.']);
        }
        $slug = slugify($data['slug'] ?? $name);
        if ($this->catalog->ingredientBySlug($slug) !== null) {
            throw new ValidationException(['slug' => 'Ya existe un ingrediente con ese slug.']);
        }
        $id = $this->catalog->createIngredient([
            'name' => $name,
            'slug' => $slug,
            'category' => $data['category'] ?? 'otro',
            'brix' => $data['brix'] !== '' ? (float) $data['brix'] : null,
            'sugar_g_per_100' => $data['sugar_g_per_100'] !== '' ? (float) $data['sugar_g_per_100'] : null,
            'water_pct' => $data['water_pct'] !== '' ? (float) $data['water_pct'] : null,
            'abv_pct' => $data['abv_pct'] !== '' ? (float) $data['abv_pct'] : null,
            'source' => 'admin',
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->log('ingredient.created', 'ingredient', $id, $adminId, ['name' => $name]);
        return $id;
    }

    public function updateIngredient(int $adminId, int $id, array $data): void
    {
        $ingredient = $this->catalog->ingredient($id);
        if ($ingredient === null) {
            throw HttpException::notFound('El ingrediente no existe.');
        }
        $this->catalog->updateIngredient($id, [
            'name' => $data['name'] ?? $ingredient['name'],
            'category' => $data['category'] ?? $ingredient['category'],
            'brix' => $data['brix'] !== '' && $data['brix'] !== null ? (float) $data['brix'] : $ingredient['brix'],
            'sugar_g_per_100' => $data['sugar_g_per_100'] ?? $ingredient['sugar_g_per_100'],
            'water_pct' => $data['water_pct'] ?? $ingredient['water_pct'],
            'abv_pct' => $data['abv_pct'] ?? $ingredient['abv_pct'],
            'notes' => $data['notes'] ?? $ingredient['notes'],
            'active' => isset($data['active']) ? (bool) $data['active'] : (bool) $ingredient['active'],
        ]);
        $this->audit->log('ingredient.updated', 'ingredient', $id, $adminId);
    }

    public function users(): array
    {
        return $this->users->allWithRoles();
    }

    public function statistics(): array
    {
        return [
            'users' => $this->users->countAll(),
            'artisans' => $this->users->countByRole('artesano'),
            'venues' => $this->users->countByRole('local'),
            'curators' => $this->users->countByRole('comisario'),
            'moderators' => $this->users->countByRole('moderador'),
        ];
    }
}
