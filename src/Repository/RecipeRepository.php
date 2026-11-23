<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class RecipeRepository
{
    /**
     * @param array{q?:string, style?:string, territory?:string, abv_min?:float, abv_max?:float, sort?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function publicList(array $filters = [], int $limit = 24, int $offset = 0): array
    {
        $where = ["1 = 1"];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(title LIKE ? OR description LIKE ? OR studio_name LIKE ? OR style LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term, $term, $term);
        }
        if (!empty($filters['style'])) {
            $where[] = 'style = ?';
            $params[] = $filters['style'];
        }
        if (!empty($filters['territory'])) {
            $where[] = 'territory LIKE ?';
            $params[] = '%' . $filters['territory'] . '%';
        }
        if (isset($filters['abv_min']) && $filters['abv_min'] !== '') {
            $where[] = 'abv >= ?';
            $params[] = (float) $filters['abv_min'];
        }
        if (isset($filters['abv_max']) && $filters['abv_max'] !== '') {
            $where[] = 'abv <= ?';
            $params[] = (float) $filters['abv_max'];
        }

        $order = match ($filters['sort'] ?? 'recent') {
            'abv' => 'abv DESC, title',
            'coherence' => 'avg_coherence DESC, title',
            'forks' => 'fork_count DESC, title',
            default => 'updated_at DESC, title',
        };

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        return Database::select(
            'SELECT * FROM v_recipe_catalog WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $order . ' LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
    }

    public function countPublic(array $filters = []): int
    {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(title LIKE ? OR description LIKE ? OR studio_name LIKE ? OR style LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term, $term, $term);
        }
        if (!empty($filters['style'])) {
            $where[] = 'style = ?';
            $params[] = $filters['style'];
        }
        if (!empty($filters['territory'])) {
            $where[] = 'territory LIKE ?';
            $params[] = '%' . $filters['territory'] . '%';
        }
        if (isset($filters['abv_min']) && $filters['abv_min'] !== '') {
            $where[] = 'abv >= ?';
            $params[] = (float) $filters['abv_min'];
        }
        if (isset($filters['abv_max']) && $filters['abv_max'] !== '') {
            $where[] = 'abv <= ?';
            $params[] = (float) $filters['abv_max'];
        }
        return (int) Database::value('SELECT COUNT(*) FROM v_recipe_catalog WHERE ' . implode(' AND ', $where), $params);
    }

    public function findBySlug(string $slug): ?array
    {
        return Database::first('SELECT * FROM v_recipe_catalog WHERE slug = ?', [$slug]);
    }

    public function findById(int $id): ?array
    {
        return Database::first(
            'SELECT r.*, u.display_name, ap.studio_name, ap.slug AS artisan_slug, ap.territory
             FROM recipes r
             JOIN users u ON u.id = r.author_id
             LEFT JOIN artisan_profiles ap ON ap.user_id = r.author_id
             WHERE r.id = ?',
            [$id]
        );
    }

    public function slugExists(string $slug): bool
    {
        return (bool) Database::value('SELECT COUNT(*) FROM recipes WHERE slug = ?', [$slug]);
    }

    /** @return array<int, array<string, mixed>> */
    public function versions(int $recipeId): array
    {
        return Database::select(
            'SELECT rv.*, u.display_name AS author_name
             FROM recipe_versions rv JOIN users u ON u.id = rv.created_by
             WHERE rv.recipe_id = ? ORDER BY rv.version_no DESC',
            [$recipeId]
        );
    }

    public function version(int $versionId): ?array
    {
        return Database::first(
            'SELECT rv.*, r.slug, r.title, r.author_id, r.style, r.description
             FROM recipe_versions rv JOIN recipes r ON r.id = rv.recipe_id
             WHERE rv.id = ?',
            [$versionId]
        );
    }

    public function currentVersion(int $recipeId): ?array
    {
        return Database::first(
            'SELECT rv.* FROM recipe_versions rv
             JOIN recipes r ON r.current_version_id = rv.id
             WHERE r.id = ?',
            [$recipeId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function byAuthor(int $authorId): array
    {
        return Database::select(
            'SELECT c.*, r.visibility
               FROM v_recipe_catalog c
               JOIN recipes r ON r.id = c.id
              WHERE c.author_id = ?
              ORDER BY c.updated_at DESC',
            [$authorId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        return Database::select('SELECT * FROM v_recipe_catalog ORDER BY updated_at DESC LIMIT ' . $limit);
    }

    /** @return array<int, string> */
    public function styles(): array
    {
        return array_map(
            static fn(array $row): string => (string) $row['style'],
            Database::select('SELECT DISTINCT style FROM v_recipe_catalog ORDER BY style')
        );
    }

    public function create(array $data, array $version): array
    {
        return Database::transaction(function () use ($data, $version): array {
            $recipeId = Database::insert(
                'INSERT INTO recipes (slug, author_id, title, style, description, tags_json, visibility, forked_from_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (string) $data['slug'],
                    (int) $data['author_id'],
                    (string) $data['title'],
                    (string) $data['style'],
                    $data['description'] ?? null,
                    json_encode($data['tags'] ?? [], JSON_UNESCAPED_UNICODE),
                    $data['visibility'] ?? 'publica',
                    $data['forked_from_id'] ?? null,
                ]
            );

            $versionId = Database::insert(
                'INSERT INTO recipe_versions (recipe_id, version_no, body_json, abv, brix, final_volume_ml, notes, changelog, created_by, published_at)
                 VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $recipeId,
                    json_encode($version['body'], JSON_UNESCAPED_UNICODE),
                    $version['abv'] ?? null,
                    $version['brix'] ?? null,
                    $version['final_volume_ml'] ?? null,
                    $version['notes'] ?? null,
                    $version['changelog'] ?? 'Versión inicial',
                    (int) $data['author_id'],
                ]
            );

            Database::execute('UPDATE recipes SET current_version_id = ? WHERE id = ?', [$versionId, $recipeId]);
            return ['recipe_id' => $recipeId, 'version_id' => $versionId];
        });
    }

    public function addVersion(int $recipeId, array $version, int $createdBy): int
    {
        return Database::transaction(function () use ($recipeId, $version, $createdBy): int {
            $next = (int) Database::value(
                'SELECT COALESCE(MAX(version_no), 0) + 1 FROM recipe_versions WHERE recipe_id = ?',
                [$recipeId]
            );
            $versionId = Database::insert(
                'INSERT INTO recipe_versions (recipe_id, version_no, body_json, abv, brix, final_volume_ml, notes, changelog, created_by, published_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $recipeId,
                    $next,
                    json_encode($version['body'], JSON_UNESCAPED_UNICODE),
                    $version['abv'] ?? null,
                    $version['brix'] ?? null,
                    $version['final_volume_ml'] ?? null,
                    $version['notes'] ?? null,
                    $version['changelog'] ?? null,
                    $createdBy,
                ]
            );
            Database::execute(
                'UPDATE recipes SET current_version_id = ?, updated_at = NOW() WHERE id = ?',
                [$versionId, $recipeId]
            );
            return $versionId;
        });
    }

    public function registerFork(int $originalId): void
    {
        Database::execute('UPDATE recipes SET fork_count = fork_count + 1 WHERE id = ?', [$originalId]);
    }

    public function setVisibility(int $recipeId, string $visibility): void
    {
        Database::execute('UPDATE recipes SET visibility = ? WHERE id = ?', [$visibility, $recipeId]);
    }

    /** @return array<int, array<string, mixed>> */
    public function lotsForRecipe(int $recipeId): array
    {
        return Database::select(
            "SELECT * FROM v_lot_public
             WHERE recipe_id = ? AND status NOT IN ('descartado','bloqueado')
             ORDER BY coherence_score DESC",
            [$recipeId]
        );
    }

    public function stats(): array
    {
        return [
            'recipes' => (int) Database::value('SELECT COUNT(*) FROM recipes WHERE visibility = "publica"'),
            'versions' => (int) Database::value('SELECT COUNT(*) FROM recipe_versions'),
            'forks' => (int) Database::value('SELECT COALESCE(SUM(fork_count), 0) FROM recipes'),
            'styles' => count($this->styles()),
        ];
    }
}
