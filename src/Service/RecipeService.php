<?php
declare(strict_types=1);

namespace App\Service;

use App\Calculation\RecipeEngine;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repository\AuditRepository;
use App\Repository\LotRepository;
use App\Repository\ProfileRepository;
use App\Repository\RecipeRepository;

final class RecipeService
{
    private RecipeRepository $recipes;
    private AuditRepository $audit;
    private ProfileRepository $profiles;
    private LotRepository $lots;

    public function __construct()
    {
        $this->recipes = new RecipeRepository();
        $this->audit = new AuditRepository();
        $this->profiles = new ProfileRepository();
        $this->lots = new LotRepository();
    }

    public function search(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(48, $perPage));
        $total = $this->recipes->countPublic($filters);
        $items = $this->recipes->publicList($filters, $perPage, ($page - 1) * $perPage);
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'styles' => $this->recipes->styles(),
        ];
    }

    public function detail(string $slug): array
    {
        $recipe = $this->recipes->findBySlug($slug);
        if ($recipe === null) {
            throw HttpException::notFound('Esa receta no existe o no es pública.');
        }
        $versions = $this->recipes->versions((int) $recipe['id']);
        $current = $this->recipes->currentVersion((int) $recipe['id']);
        $body = json_data($current['body_json'] ?? null);
        $engineInput = $body['engine'] ?? [];
        $computed = $engineInput !== [] ? RecipeEngine::calculate($engineInput) : null;

        return [
            'recipe' => $recipe,
            'versions' => $versions,
            'current_version' => $current,
            'body' => $body,
            'computed' => $computed,
            'lots' => $this->recipes->lotsForRecipe((int) $recipe['id']),
            'author' => $this->profiles->artisan((int) $recipe['author_id']),
        ];
    }

    public function create(int $authorId, array $data): int
    {
        if (!AuthService::hasRole('artesano')) {
            throw HttpException::forbidden('Sólo un artesano puede publicar recetas.');
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException(['title' => 'El título es obligatorio.']);
        }
        if (mb_strlen($title) < 4) {
            throw new ValidationException(['title' => 'El título debe tener al menos 4 caracteres.']);
        }

        $engineInput = $this->engineInput($data);
        $computed = RecipeEngine::calculate($engineInput);
        $slug = $this->uniqueSlug($data['slug'] ?? $title);
        $tags = $this->parseTags($data['tags'] ?? '');
        $style = trim((string) ($data['style'] ?? 'Licor de fruta'));

        $ids = $this->recipes->create([
            'slug' => $slug,
            'author_id' => $authorId,
            'title' => $title,
            'style' => $style,
            'description' => trim((string) ($data['description'] ?? '')),
            'tags' => $tags,
            'visibility' => in_array(($data['visibility'] ?? 'publica'), ['publica', 'no_listada', 'privada'], true)
                ? ($data['visibility'] ?? 'publica')
                : 'publica',
        ], [
            'body' => $this->body($engineInput, $computed),
            'abv' => $computed['abv_pct'],
            'brix' => $computed['brix'],
            'final_volume_ml' => $computed['volumes']['final_ml'],
            'notes' => $data['notes'] ?? null,
            'changelog' => 'Versión inicial',
        ]);

        $this->audit->log('recipe.created', 'recipe', $ids['recipe_id'], $authorId, [
            'slug' => $slug,
            'title' => $title,
            'abv' => $computed['abv_pct'],
        ]);

        return $ids['recipe_id'];
    }

    public function addVersion(int $recipeId, int $userId, array $data): int
    {
        $recipe = $this->recipes->findById($recipeId);
        if ($recipe === null) {
            throw HttpException::notFound('La receta no existe.');
        }
        if ((int) $recipe['author_id'] !== $userId && !AuthService::hasRole('admin')) {
            throw HttpException::forbidden('No puedes versionar una receta que no es tuya.');
        }
        $engineInput = $this->engineInput($data);
        $computed = RecipeEngine::calculate($engineInput);
        $versionId = $this->recipes->addVersion($recipeId, [
            'body' => $this->body($engineInput, $computed),
            'abv' => $computed['abv_pct'],
            'brix' => $computed['brix'],
            'final_volume_ml' => $computed['volumes']['final_ml'],
            'notes' => $data['notes'] ?? null,
            'changelog' => trim((string) ($data['changelog'] ?? 'Ajuste de la receta')),
        ], $userId);

        $this->audit->log('recipe.versioned', 'recipe', $recipeId, $userId, [
            'version_id' => $versionId,
            'abv' => $computed['abv_pct'],
        ]);
        return $versionId;
    }

    public function fork(int $recipeId, int $userId, ?string $title = null): int
    {
        $original = $this->recipes->findById($recipeId);
        if ($original === null || $original['visibility'] === 'privada') {
            throw HttpException::notFound('No puedes bifurcar esa receta.');
        }
        $current = $this->recipes->currentVersion($recipeId);
        if ($current === null) {
            throw HttpException::notFound('La receta no tiene versión vigente.');
        }
        $body = json_data($current['body_json']);
        $computed = RecipeEngine::calculate($body['engine'] ?? []);
        $title = $title ?: ($original['title'] . ' (bifurcación)');
        $ids = $this->recipes->create([
            'slug' => $this->uniqueSlug($title . '-fork'),
            'author_id' => $userId,
            'title' => $title,
            'style' => $original['style'],
            'description' => 'Bifurcada de «' . $original['title'] . '». ' . (string) ($original['description'] ?? ''),
            'tags' => json_data($original['tags_json'] ?? null),
            'visibility' => 'publica',
            'forked_from_id' => $recipeId,
        ], [
            'body' => $this->body($body['engine'] ?? [], $computed),
            'abv' => $computed['abv_pct'],
            'brix' => $computed['brix'],
            'final_volume_ml' => $computed['volumes']['final_ml'],
            'notes' => 'Bifurcación de la versión ' . $current['version_no'],
            'changelog' => 'Bifurcación de «' . $original['title'] . '»',
        ]);
        $this->recipes->registerFork($recipeId);
        $this->audit->log('recipe.forked', 'recipe', $ids['recipe_id'], $userId, [
            'from_recipe' => $recipeId,
        ]);
        return $ids['recipe_id'];
    }

    public function scale(int $recipeId, float $factor): array
    {
        $current = $this->recipes->currentVersion($recipeId);
        if ($current === null) {
            throw HttpException::notFound('La receta no tiene versión vigente.');
        }
        $body = json_data($current['body_json']);
        return RecipeEngine::scale($body['engine'] ?? [], $factor);
    }

    public function changeVisibility(int $recipeId, int $userId, string $visibility): void
    {
        $recipe = $this->recipes->findById($recipeId);
        if ($recipe === null) {
            throw HttpException::notFound('La receta no existe.');
        }
        if ((int) $recipe['author_id'] !== $userId && !AuthService::hasRole('admin')) {
            throw HttpException::forbidden('No puedes cambiar esa receta.');
        }
        if (!in_array($visibility, ['publica', 'no_listada', 'privada'], true)) {
            throw HttpException::badRequest('Visibilidad no válida.');
        }
        $this->recipes->setVisibility($recipeId, $visibility);
        $this->audit->log('recipe.visibility', 'recipe', $recipeId, $userId, ['visibility' => $visibility]);
    }

    public function authorRecipes(int $authorId): array
    {
        return $this->recipes->byAuthor($authorId);
    }

    public function latestInput(int $recipeId): array
    {
        $current = $this->recipes->currentVersion($recipeId);
        if ($current === null) {
            return [];
        }
        $body = json_data($current['body_json']);
        return $body['engine'] ?? [];
    }

    private function engineInput(array $data): array
    {
        return [
            'base' => (string) ($data['base'] ?? 'cereza'),
            'mode' => (string) ($data['mode'] ?? 'ingredientes'),
            'base_abv_pct' => $data['base_abv_pct'] ?? 40,
            'vodka_ml' => $data['vodka_ml'] ?? 500,
            'ingredient_g' => $data['ingredient_g'] ?? 500,
            'desired_volume_ml' => $data['desired_volume_ml'] ?? 1000,
            'extraction' => (string) ($data['extraction'] ?? 'limoncello'),
            'extraction_loss_pct' => $data['extraction_loss_pct'] ?? null,
            'sugar_ratio' => $data['sugar_ratio'] ?? 0.40,
            'water_ratio' => $data['water_ratio'] ?? 0.50,
            'blend_enabled' => filter_var($data['blend_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'blend_pct' => $data['blend_pct'] ?? 15,
            'rest_days' => $data['rest_days'] ?? 14,
            'syrup' => (string) ($data['syrup'] ?? 'homemade'),
            'flavor' => (string) ($data['flavor'] ?? 'original'),
            'custom_spice' => (string) ($data['custom_spice'] ?? ''),
            'split' => filter_var($data['split'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function body(array $engineInput, array $computed): array
    {
        return [
            'engine' => $engineInput,
            'computed' => [
                'abv' => $computed['abv_pct'],
                'brix' => $computed['brix'],
                'sugar_gl' => $computed['sugar_gl'],
                'final_volume_ml' => $computed['volumes']['final_ml'],
                'freezing_point_c' => $computed['freeze']['freezing_point_c'],
            ],
            'timeline' => $computed['timeline'],
            'preset' => $computed['preset'],
            'extraction' => $computed['extraction'],
            'syrup' => $computed['syrup'],
            'warnings' => $computed['warnings'],
        ];
    }

    /** @return array<int, string> */
    private function parseTags(mixed $tags): array
    {
        if (is_array($tags)) {
            return array_values(array_filter(array_map('trim', $tags)));
        }
        $parts = array_filter(array_map('trim', explode(',', (string) $tags)));
        return array_values(array_slice($parts, 0, 8));
    }

    private function uniqueSlug(string $source): string
    {
        $base = slugify($source) ?: 'receta';
        $slug = $base;
        $i = 2;
        while ($this->recipes->slugExists($slug)) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }
}
