<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repository\AuditRepository;
use App\Repository\CatalogRepository;
use App\Repository\LotRepository;
use App\Repository\ProfileRepository;
use App\Repository\RecipeRepository;
use App\Repository\ReviewRepository;

final class LotService
{
    private LotRepository $lots;
    private RecipeRepository $recipes;
    private AuditRepository $audit;
    private ReviewRepository $reviews;
    private CatalogRepository $catalog;
    private ProfileRepository $profiles;

    public function __construct()
    {
        $this->lots = new LotRepository();
        $this->recipes = new RecipeRepository();
        $this->audit = new AuditRepository();
        $this->reviews = new ReviewRepository();
        $this->catalog = new CatalogRepository();
        $this->profiles = new ProfileRepository();
    }

    public function search(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(48, $perPage));
        $total = $this->lots->countPublic($filters);
        return [
            'items' => $this->lots->publicList($filters, $perPage, ($page - 1) * $perPage),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function detail(int $id): array
    {
        $lot = $this->lots->find($id);
        if ($lot === null) {
            throw HttpException::notFound('Ese lote no existe o está bloqueado.');
        }
        $verdict = $this->lots->latestVerdict($id);
        $listing = $this->lots->listingForLot($id);
        $artisan = $this->profiles->artisan((int) $lot['artisan_id']);

        return [
            'lot' => $lot,
            'readings' => $this->lots->readings($id),
            'verdict' => $verdict,
            'breakdown' => json_data($verdict['breakdown_json'] ?? null),
            'verdict_history' => $this->lots->verdictHistory($id),
            'listing' => $listing,
            'reviews' => $this->reviews->forLot($id),
            'artisan' => $artisan,
        ];
    }

    public function create(int $artisanId, array $data): int
    {
        if (!AuthService::hasRole('artesano')) {
            throw HttpException::forbidden('Sólo un artesano puede registrar lotes.');
        }

        $versionId = (int) ($data['recipe_version_id'] ?? 0);
        $version = Database::first(
            'SELECT rv.id, rv.recipe_id, r.title, rv.version_no
             FROM recipe_versions rv JOIN recipes r ON r.id = rv.recipe_id
             WHERE rv.id = ? AND r.author_id = ?',
            [$versionId, $artisanId]
        );
        if ($version === null && !AuthService::hasRole('admin')) {
            throw ValidationException(['recipe_version_id' => 'Debes elegir una versión de una receta tuya.']);
        }
        if ($version === null) {
            $version = Database::first(
                'SELECT rv.id, rv.recipe_id, r.title, rv.version_no
                 FROM recipe_versions rv JOIN recipes r ON r.id = rv.recipe_id WHERE rv.id = ?',
                [$versionId]
            );
        }
        if ($version === null) {
            throw ValidationException(['recipe_version_id' => 'La versión de receta no existe.']);
        }

        $initialVolume = (float) ($data['initial_volume_l'] ?? 0);
        $initialAbv = (float) ($data['initial_abv'] ?? 0);
        $startedAt = (string) ($data['started_at'] ?? date('Y-m-d'));
        $units = (int) ($data['units_total'] ?? 0);

        if ($initialVolume <= 0) {
            throw ValidationException(['initial_volume_l' => 'El volumen inicial debe ser positivo.']);
        }
        if ($initialAbv <= 0 || $initialAbv > 90) {
            throw ValidationException(['initial_abv' => 'La graduación inicial debe estar entre 0 y 90.']);
        }
        if (strtotime($startedAt) === false || strtotime($startedAt) > strtotime('today')) {
            throw ValidationException(['started_at' => 'La fecha de inicio no puede ser futura.']);
        }
        if ($units <= 0) {
            throw ValidationException(['units_total' => 'Indica cuántas unidades produce el lote.']);
        }

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = $this->nextCode();
        }
        if (Database::value('SELECT COUNT(*) FROM lots WHERE code = ?', [$code])) {
            throw ValidationException(['code' => 'Ese código de lote ya existe.']);
        }

        $territory = (string) ($data['territory'] ?? 'Comunitat Valenciana');
        $lotId = $this->lots->create([
            'code' => $code,
            'artisan_id' => $artisanId,
            'recipe_version_id' => $versionId,
            'barrel_ref' => $data['barrel_ref'] ?? null,
            'territory' => $territory,
            'initial_volume_l' => $initialVolume,
            'current_volume_l' => $initialVolume,
            'initial_abv' => $initialAbv,
            'declared_abv' => $initialAbv,
            'started_at' => $startedAt,
            'status' => in_array(($data['status'] ?? 'en_maceracion'), ['en_maceracion', 'en_reposo', 'embotellado', 'conservado'], true)
                ? $data['status'] : 'en_maceracion',
            'units_total' => $units,
            'unit_ml' => (int) ($data['unit_ml'] ?? 700),
            'price_per_unit' => $data['price_per_unit'] ?? null,
            'is_last_of_barrel' => !empty($data['is_last_of_barrel']),
            'notes' => $data['notes'] ?? null,
        ]);

        // La primera lectura es obligatoria: un lote sin lecturas no tiene
        // evidencia física y no puede generar confianza.
        Database::call('sp_registrar_lectura', [
            $lotId,
            $initialVolume,
            $initialAbv,
            $data['temperature_c'] ?? null,
            $startedAt,
            $artisanId,
        ], ['score', 'breakdown']);

        $this->audit->log('lot.created', 'lot', $lotId, $artisanId, [
            'code' => $code,
            'recipe' => $version['title'],
            'volume_l' => $initialVolume,
            'abv' => $initialAbv,
        ]);

        return $lotId;
    }

    public function addReading(int $artisanId, int $lotId, array $data): array
    {
        $lot = $this->lots->findRaw($lotId);
        if ($lot === null) {
            throw HttpException::notFound('El lote no existe.');
        }
        if ((int) $lot['artisan_id'] !== $artisanId && !AuthService::hasRole('admin')) {
            throw HttpException::forbidden('No puedes registrar lecturas en un lote ajeno.');
        }

        $volume = (float) ($data['volume_l'] ?? 0);
        $abv = (float) ($data['abv'] ?? 0);
        $takenAt = (string) ($data['taken_at'] ?? date('Y-m-d'));

        if ($volume <= 0) {
            throw ValidationException(['volume_l' => 'El volumen debe ser positivo.']);
        }
        if ($abv <= 0 || $abv > 90) {
            throw ValidationException(['abv' => 'La graduación debe estar entre 0 y 90.']);
        }

        $result = Database::call('sp_registrar_lectura', [
            $lotId,
            $volume,
            $abv,
            isset($data['temperature_c']) && $data['temperature_c'] !== '' ? (float) $data['temperature_c'] : null,
            $takenAt,
            $artisanId,
        ], ['score', 'breakdown']);

        $this->audit->log('lot.reading', 'lot', $lotId, $artisanId, [
            'taken_at' => $takenAt,
            'volume_l' => $volume,
            'abv' => $abv,
            'score' => $result['score'] ?? null,
        ]);

        return [
            'score' => (int) ($result['score'] ?? 0),
            'breakdown' => json_data($result['breakdown'] ?? null),
        ];
    }

    public function recompute(int $lotId): array
    {
        $result = Database::call('sp_coherencia_lote', [$lotId], ['score', 'breakdown']);
        return [
            'score' => (int) ($result['score'] ?? 0),
            'breakdown' => json_data($result['breakdown'] ?? null),
        ];
    }

    public function verdict(int $lotId): array
    {
        $latest = $this->lots->latestVerdict($lotId);
        if ($latest === null) {
            return $this->recompute($lotId);
        }
        return [
            'score' => (int) $latest['score'],
            'breakdown' => json_data($latest['breakdown_json']),
            'computed_at' => $latest['computed_at'],
        ];
    }

    public function publishListing(int $artisanId, int $lotId, array $data): int
    {
        $lot = $this->lots->findRaw($lotId);
        if ($lot === null) {
            throw HttpException::notFound('El lote no existe.');
        }
        if ((int) $lot['artisan_id'] !== $artisanId) {
            throw HttpException::forbidden('No puedes publicar un lote ajeno.');
        }
        $minScore = $this->catalog->threshold('trust.listing_min_score', 70);
        if ((int) $lot['coherence_score'] < $minScore) {
            throw HttpException::badRequest(
                'El lote tiene coherencia ' . (int) $lot['coherence_score'] . '/100 y no alcanza el umbral de '
                . (int) $minScore . '. Registra más lecturas o corrige la declaración antes de publicar.'
            );
        }

        $available = $this->lots->unitsAvailable($lotId);
        $units = (int) ($data['units_available'] ?? 0);
        if ($units <= 0 || $units > $available) {
            throw ValidationException(['units_available' => "Sólo hay {$available} unidades disponibles."]);
        }

        $existing = $this->lots->listingForLot($lotId);
        $listingId = 0;
        if ($existing !== null) {
            $listingId = (int) $existing['id'];
            $this->lots->updateListing($listingId, [
                'title' => (string) ($data['title'] ?? $existing['title']),
                'description' => $data['description'] ?? $existing['description'],
                'units_available' => $units,
                'price_per_unit' => (float) ($data['price_per_unit'] ?? $existing['price_per_unit']),
                'status' => 'publicado',
            ]);
            $this->audit->log('listing.updated', 'listing', $listingId, $artisanId, ['lot_id' => $lotId, 'status' => 'publicado']);
        } else {
            $listingId = $this->lots->createListing([
                'lot_id' => $lotId,
                'artisan_id' => $artisanId,
                'title' => (string) ($data['title'] ?? ('Lote ' . $lot['code'])),
                'description' => $data['description'] ?? null,
                'units_available' => $units,
                'price_per_unit' => (float) ($data['price_per_unit'] ?? $lot['price_per_unit'] ?? 0),
                'status' => 'publicado',
            ]);
            $this->audit->log('listing.published', 'listing', $listingId, $artisanId, ['lot_id' => $lotId, 'units' => $units]);
        }

        return $listingId;
    }

    public function changeStatus(int $artisanId, int $lotId, string $status): void
    {
        $lot = $this->lots->findRaw($lotId);
        if ($lot === null) {
            throw HttpException::notFound('El lote no existe.');
        }
        if ((int) $lot['artisan_id'] !== $artisanId && !AuthService::hasRole('admin')) {
            throw HttpException::forbidden('No puedes cambiar ese lote.');
        }
        $allowed = [
            'en_maceracion' => ['en_reposo', 'descartado', 'bloqueado'],
            'en_reposo' => ['embotellado', 'descartado', 'bloqueado'],
            'embotellado' => ['conservado', 'descartado'],
            'conservado' => ['descartado'],
            'descartado' => [],
            'bloqueado' => [],
        ];
        if (!in_array($status, $allowed[$lot['status']] ?? [], true)) {
            throw HttpException::badRequest('Transición de estado no permitida: ' . $lot['status'] . ' → ' . $status);
        }
        $this->lots->updateStatus($lotId, $status);
        $this->audit->log('lot.status', 'lot', $lotId, $artisanId, ['from' => $lot['status'], 'to' => $status]);
    }

    public function byArtisan(int $artisanId): array
    {
        return $this->lots->byArtisan($artisanId);
    }

    public function readings(int $lotId): array
    {
        return $this->lots->readings($lotId);
    }

    public function listingForLot(int $lotId): ?array
    {
        return $this->lots->listingForLot($lotId);
    }

    public function publicListings(int $limit = 12): array
    {
        return $this->lots->publicListings($limit);
    }

    public function recent(int $limit = 4): array
    {
        return $this->lots->recent($limit);
    }

    public function stats(): array
    {
        return $this->lots->stats();
    }

    public function availableUnits(int $lotId): int
    {
        return $this->lots->unitsAvailable($lotId);
    }

    private function nextCode(): string
    {
        $year = date('Y');
        $count = (int) Database::value('SELECT COUNT(*) FROM lots');
        $code = sprintf('LOT-%s-%04d', $year, $count + 1);
        $i = $count + 2;
        while (Database::value('SELECT COUNT(*) FROM lots WHERE code = ?', [$code])) {
            $code = sprintf('LOT-%s-%04d', $year, $i);
            $i++;
        }
        return $code;
    }
}
