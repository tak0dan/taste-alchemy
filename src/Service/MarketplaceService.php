<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repository\AuditRepository;
use App\Repository\CatalogRepository;
use App\Repository\CommissionRepository;
use App\Repository\LotRepository;
use App\Repository\ModerationRepository;
use App\Repository\ProposalRepository;
use App\Repository\RequestRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;

/**
 * El mecanismo de tiraje: petición → propuesta → comisión → consignación o
 * factura → valoración verificada. La aceptación se delega al procedimiento
 * transaccional sp_aceptar_propuesta.
 */
final class MarketplaceService
{
    private RequestRepository $requests;
    private ProposalRepository $proposals;
    private CommissionRepository $commissions;
    private LotRepository $lots;
    private ReviewRepository $reviews;
    private AuditRepository $audit;
    private NotificationService $notifications;
    private CatalogRepository $catalog;
    private ModerationRepository $moderation;

    public function __construct()
    {
        $this->requests = new RequestRepository();
        $this->proposals = new ProposalRepository();
        $this->commissions = new CommissionRepository();
        $this->lots = new LotRepository();
        $this->reviews = new ReviewRepository();
        $this->audit = new AuditRepository();
        $this->notifications = new NotificationService();
        $this->catalog = new CatalogRepository();
        $this->moderation = new ModerationRepository();
    }

    public function openRequests(array $filters = [], int $limit = 30): array
    {
        return $this->requests->open($filters, $limit);
    }

    public function requestDetail(int $requestId, int $viewerId, bool $isVenueOwner = false): array
    {
        $request = $this->requests->find($requestId);
        if ($request === null) {
            throw HttpException::notFound('La petición no existe.');
        }
        $proposals = $this->proposals->forRequest($requestId);
        if (!$isVenueOwner) {
            // Un artesano sólo ve sus propias propuestas; el local ve todas.
            $proposals = array_values(array_filter(
                $proposals,
                static fn(array $p): bool => (int) $p['artisan_id'] === $viewerId
            ));
        }
        return [
            'request' => $request,
            'proposals' => $proposals,
        ];
    }

    public function createRequest(int $venueId, array $data): int
    {
        if (!AuthService::hasRole('local')) {
            throw HttpException::forbidden('Sólo un local puede abrir peticiones.');
        }
        if (!AuthService::isVerifiedVenue()) {
            throw HttpException::forbidden('La licencia del local debe estar verificada antes de pedir.');
        }

        $title = trim((string) ($data['title'] ?? ''));
        $style = trim((string) ($data['style'] ?? ''));
        $units = (int) ($data['units_wanted'] ?? 0);
        $target = (string) ($data['target_date'] ?? '');
        $abvMin = ($data['abv_min'] ?? '') !== '' && ($data['abv_min'] ?? null) !== null ? (float) $data['abv_min'] : null;
        $abvMax = ($data['abv_max'] ?? '') !== '' && ($data['abv_max'] ?? null) !== null ? (float) $data['abv_max'] : null;

        $errors = [];
        if (mb_strlen($title) < 4) {
            $errors['title'] = 'Describe en pocas palabras qué buscas.';
        }
        if ($style === '') {
            $errors['style'] = 'Indica el estilo de licor.';
        }
        if ($units <= 0) {
            $errors['units_wanted'] = 'Indica cuántas unidades quieres.';
        }
        if (strtotime($target) === false || strtotime($target) < strtotime('today')) {
            $errors['target_date'] = 'La fecha objetivo debe ser futura.';
        }
        if ($abvMin !== null && $abvMax !== null && $abvMin > $abvMax) {
            $errors['abv_max'] = 'La graduación máxima no puede ser menor que la mínima.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $id = $this->requests->create([
            'venue_id' => $venueId,
            'title' => $title,
            'style' => $style,
            'description' => trim((string) ($data['description'] ?? '')),
            'territory' => trim((string) ($data['territory'] ?? 'Comunitat Valenciana')),
            'units_wanted' => $units,
            'abv_min' => $abvMin,
            'abv_max' => $abvMax,
            'target_date' => $target,
            'exclusivity_requested' => !empty($data['exclusivity_requested']),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $this->audit->log('request.created', 'request', $id, $venueId, [
            'title' => $title,
            'units' => $units,
            'territory' => $data['territory'] ?? null,
        ]);

        // La demanda se crea publicando: se avisa a los artesanos.
        $artisans = Database::select(
            'SELECT DISTINCT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id JOIN users u ON u.id = ur.user_id
             WHERE r.code = "artesano" AND u.status = "activo"'
        );
        foreach ($artisans as $artisan) {
            $this->notifications->notify(
                (int) $artisan['user_id'],
                'request.opened',
                'Nueva petición: ' . $title,
                $units . ' unidades · ' . ($data['territory'] ?? 'Comunitat Valenciana'),
                '/workshop/opportunities'
            );
        }

        return $id;
    }

    public function propose(int $artisanId, int $requestId, array $data): int
    {
        if (!AuthService::hasRole('artesano')) {
            throw HttpException::forbidden('Sólo un artesano puede proponer lotes.');
        }
        $request = $this->requests->find($requestId);
        if ($request === null) {
            throw HttpException::notFound('La petición no existe.');
        }
        if ($request['status'] !== 'abierta') {
            throw HttpException::badRequest('La petición ya no está abierta.');
        }

        $lotId = (int) ($data['lot_id'] ?? 0);
        $lot = $this->lots->find($lotId);
        if ($lot === null) {
            throw ValidationException(['lot_id' => 'El lote no existe.']);
        }
        if ((int) $lot['artisan_id'] !== $artisanId) {
            throw HttpException::forbidden('Sólo puedes proponer lotes propios.');
        }
        if (in_array($lot['status'], ['descartado', 'bloqueado', 'en_maceracion'], true)) {
            throw HttpException::badRequest('El lote aún no puede servirse.');
        }

        $minScore = $this->catalog->threshold('trust.listing_min_score', 70);
        if ((int) $lot['coherence_score'] < $minScore) {
            throw HttpException::badRequest(
                'El lote tiene coherencia ' . (int) $lot['coherence_score'] . '/100; el mercado exige al menos '
                . (int) $minScore . ' para poder proponerlo.'
            );
        }

        $units = (int) ($data['units'] ?? 0);
        $price = (float) ($data['price_per_unit'] ?? 0);
        $mode = (string) ($data['mode'] ?? 'ambas');
        $available = $this->lots->unitsAvailable($lotId);

        $errors = [];
        if ($units <= 0) {
            $errors['units'] = 'Indica las unidades que ofreces.';
        } elseif ($units > $available) {
            $errors['units'] = "El lote sólo tiene {$available} unidades disponibles.";
        }
        if ($price <= 0) {
            $errors['price_per_unit'] = 'Indica un precio por unidad.';
        }
        if (!in_array($mode, ['venta', 'consignacion', 'ambas'], true)) {
            $errors['mode'] = 'Modo de suministro no válido.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if ($this->proposals->exists($requestId, $lotId, $artisanId)) {
            throw HttpException::conflict('Ya has propuesto ese lote a esta petición.');
        }

        $proposalId = $this->proposals->create([
            'request_id' => $requestId,
            'lot_id' => $lotId,
            'artisan_id' => $artisanId,
            'units' => $units,
            'price_per_unit' => $price,
            'mode' => $mode,
            'exclusivity' => !empty($data['exclusivity']),
            'exclusivity_fee' => (float) ($data['exclusivity_fee'] ?? 0),
            'message' => trim((string) ($data['message'] ?? '')),
        ]);

        $this->audit->log('proposal.created', 'proposal', $proposalId, $artisanId, [
            'request_id' => $requestId,
            'lot_id' => $lotId,
            'units' => $units,
            'price' => $price,
            'coherence' => (int) $lot['coherence_score'],
        ]);

        $this->notifications->notify(
            (int) $request['venue_id'],
            'proposal.received',
            'Nueva propuesta para «' . $request['title'] . '»',
            $lot['code'] . ' · ' . $units . ' unidades · coherencia ' . $lot['coherence_score'] . '/100',
            '/bar/requests/' . $requestId
        );

        return $proposalId;
    }

    public function withdrawProposal(int $artisanId, int $proposalId): void
    {
        $proposal = $this->proposals->find($proposalId);
        if ($proposal === null) {
            throw HttpException::notFound('La propuesta no existe.');
        }
        if ((int) $proposal['artisan_id'] !== $artisanId) {
            throw HttpException::forbidden('Esa propuesta no es tuya.');
        }
        $this->proposals->withdraw($proposalId, $artisanId);
        $this->audit->log('proposal.withdrawn', 'proposal', $proposalId, $artisanId);
    }

    /**
     * Acepta una propuesta y dispara el mecanismo de tiraje. Toda la operación
     * ocurre dentro de sp_aceptar_propuesta, con bloqueo de fila del lote.
     */
    public function accept(int $venueId, int $proposalId, string $mode): int
    {
        if (!AuthService::hasRole('local')) {
            throw HttpException::forbidden('Sólo un local puede aceptar propuestas.');
        }
        $proposal = $this->proposals->find($proposalId);
        if ($proposal === null) {
            throw HttpException::notFound('La propuesta no existe.');
        }

        $result = Database::call('sp_aceptar_propuesta', [
            $proposalId,
            $venueId,
            $mode,
        ], ['commission_id', 'message']);

        $commissionId = (int) ($result['commission_id'] ?? 0);
        if ($commissionId <= 0) {
            throw HttpException::conflict('No se pudo cerrar la comisión.');
        }

        $commission = $this->commissions->find($commissionId);
        $this->audit->log('commission.accepted', 'commission', $commissionId, $venueId, [
            'proposal_id' => $proposalId,
            'lot_id' => $proposal['lot_id'],
            'mode' => $mode,
            'units' => (int) $proposal['units'],
            'total' => $commission['total_amount'] ?? null,
            'exclusivity' => (int) ($commission['exclusivity_granted'] ?? 0),
        ]);

        $this->notifications->notify(
            (int) $proposal['artisan_id'],
            'commission.created',
            'Comisión cerrada: ' . $proposal['lot_code'],
            $proposal['units'] . ' unidades · ' . ($mode === 'venta' ? 'venta' : 'consignación'),
            '/workshop/commissions/' . $commissionId
        );

        // Las propuestas perdedoras reciben aviso.
        foreach ($this->proposals->forRequest((int) $proposal['request_id']) as $other) {
            if ((int) $other['id'] !== $proposalId && $other['status'] === 'rechazada') {
                $this->notifications->notify(
                    (int) $other['artisan_id'],
                    'proposal.rejected',
                    'Propuesta no seleccionada',
                    'El local ha cerrado la petición «' . $other['request_title'] . '» con otro lote.',
                    '/workshop/opportunities'
                );
            }
        }

        return $commissionId;
    }

    public function completeDelivery(int $venueId, int $commissionId): void
    {
        $commission = $this->commissions->find($commissionId);
        if ($commission === null) {
            throw HttpException::notFound('La comisión no existe.');
        }
        if ((int) $commission['venue_id'] !== $venueId) {
            throw HttpException::forbidden('Sólo el local de la comisión puede confirmar la entrega.');
        }
        if ($commission['mode'] !== 'venta') {
            throw HttpException::badRequest('Las consignaciones se cierran desde su plazo, no con entrega directa.');
        }
        if (!in_array($commission['status'], ['abierta', 'en_ejecucion'], true)) {
            throw HttpException::badRequest('La comisión ya no admite confirmación de entrega.');
        }

        Database::execute(
            'UPDATE commissions SET status = "completada", completed_at = NOW() WHERE id = ?',
            [$commissionId]
        );
        $this->audit->log('commission.completed', 'commission', $commissionId, $venueId, [
            'mode' => 'venta',
            'units' => (int) $commission['units'],
        ]);
        $this->notifications->notify(
            (int) $commission['artisan_id'],
            'commission.completed',
            'Entrega confirmada: ' . $commission['lot_code'],
            'El local ha confirmado la recepción. La valoración llegará tras 72 horas de reflexión.',
            '/commissions/' . $commissionId
        );
    }

    public function closeConsignment(int $actorId, int $consignmentId, int $sold, int $returned): ?int
    {
        $consignment = Database::first('SELECT * FROM consignments WHERE id = ?', [$consignmentId]);
        if ($consignment === null) {
            throw HttpException::notFound('La consignación no existe.');
        }
        $commission = $this->commissions->find((int) $consignment['commission_id']);
        if ($commission === null) {
            throw HttpException::notFound('La comisión no existe.');
        }
        if ((int) $commission['venue_id'] !== $actorId && (int) $commission['artisan_id'] !== $actorId) {
            throw HttpException::forbidden('No participas en esa consignación.');
        }

        $result = Database::call('sp_cerrar_consignacion', [
            $consignmentId,
            $sold,
            $returned,
        ], ['invoice_id', 'message']);

        $invoiceId = isset($result['invoice_id']) && $result['invoice_id'] !== null
            ? (int) $result['invoice_id'] : null;

        $this->audit->log('consignment.closed', 'consignment', $consignmentId, $actorId, [
            'commission_id' => (int) $commission['id'],
            'sold' => $sold,
            'returned' => $returned,
            'invoice_id' => $invoiceId,
        ]);

        $counterparty = (int) $commission['venue_id'] === $actorId
            ? (int) $commission['artisan_id'] : (int) $commission['venue_id'];
        $this->notifications->notify(
            $counterparty,
            'consignment.closed',
            'Consignación cerrada: ' . $commission['lot_code'],
            $sold . ' vendidas · ' . $returned . ' devueltas',
            '/commissions/' . $commission['id']
        );

        return $invoiceId;
    }

    public function submitReview(int $venueId, int $commissionId, array $data): int
    {
        $commission = $this->commissions->find($commissionId);
        if ($commission === null) {
            throw HttpException::notFound('La comisión no existe.');
        }
        if ((int) $commission['venue_id'] !== $venueId) {
            throw HttpException::forbidden('Sólo el local de la comisión puede valorarla.');
        }
        if ($commission['status'] !== 'completada') {
            throw HttpException::badRequest('La comisión aún no está completada.');
        }

        $completedAt = strtotime((string) $commission['completed_at']);
        $availableAt = $completedAt + 72 * 3600;
        if ($availableAt > time()) {
            $hours = (int) ceil(($availableAt - time()) / 3600);
            throw HttpException::badRequest('Quedan ' . $hours . ' horas de reflexión antes de poder valorar.');
        }

        if ($commission['venue_review_count'] > 0) {
            throw HttpException::conflict('Ya has valorado esta comisión.');
        }

        $body = trim((string) ($data['body'] ?? ''));
        if (mb_strlen($body) < 20) {
            throw new ValidationException(['body' => 'Escribe al menos 20 caracteres de cata.']);
        }

        $scores = [];
        foreach (['aroma', 'texture', 'balance', 'finish'] as $dimension) {
            $value = $data['score_' . $dimension] ?? null;
            if ($value === '' || $value === null) {
                $scores['score_' . $dimension] = null;
                continue;
            }
            $int = (int) $value;
            if ($int < 1 || $int > 10) {
                throw new ValidationException(['score_' . $dimension => 'Las puntuaciones van de 1 a 10.']);
            }
            $scores['score_' . $dimension] = $int;
        }
        $nonNull = array_filter($scores, static fn($v) => $v !== null);
        $total = $nonNull === [] ? null : round(array_sum($nonNull) / count($nonNull), 1);

        $reviewId = $this->reviews->create([
            'commission_id' => $commissionId,
            'author_id' => $venueId,
            'kind' => 'local',
            'lot_id' => (int) $commission['lot_id'],
            'score_aroma' => $scores['score_aroma'],
            'score_texture' => $scores['score_texture'],
            'score_balance' => $scores['score_balance'],
            'score_finish' => $scores['score_finish'],
            'score_total' => $total,
            'body' => $body,
        ]);

        $this->audit->log('review.published', 'review', $reviewId, $venueId, [
            'commission_id' => $commissionId,
            'lot_id' => (int) $commission['lot_id'],
            'score_total' => $total,
        ]);

        $this->notifications->notify(
            (int) $commission['artisan_id'],
            'review.received',
            'Valoración verificada de ' . $commission['venue_name'],
            'Puntuación ' . ($total ?? '—') . '/10 sobre el lote ' . $commission['lot_code'],
            '/lots/' . $commission['lot_id']
        );

        return $reviewId;
    }

    public function submitTasting(int $curatorId, int $lotId, array $data): int
    {
        if (!AuthService::hasRole('comisario')) {
            throw HttpException::forbidden('Sólo un comisario puede publicar catas ponderadas.');
        }
        $lot = $this->lots->find($lotId);
        if ($lot === null) {
            throw HttpException::notFound('El lote no existe.');
        }
        if ((int) $lot['coherence_score'] < 70) {
            throw HttpException::badRequest('Un lote por debajo de 70 de coherencia no puede recibir una cata destacada.');
        }
        $body = trim((string) ($data['body'] ?? ''));
        if (mb_strlen($body) < 30) {
            throw new ValidationException(['body' => 'La cata debe tener al menos 30 caracteres.']);
        }
        $scores = [];
        foreach (['aroma', 'texture', 'balance', 'finish'] as $dimension) {
            $value = $data['score_' . $dimension] ?? null;
            $scores['score_' . $dimension] = ($value === '' || $value === null) ? null : max(1, min(10, (int) $value));
        }
        $nonNull = array_filter($scores, static fn($v) => $v !== null);
        $total = $nonNull === [] ? null : round(array_sum($nonNull) / count($nonNull), 1);

        $reviewId = $this->reviews->create([
            'commission_id' => null,
            'author_id' => $curatorId,
            'kind' => 'comisario',
            'lot_id' => $lotId,
            'score_aroma' => $scores['score_aroma'],
            'score_texture' => $scores['score_texture'],
            'score_balance' => $scores['score_balance'],
            'score_finish' => $scores['score_finish'],
            'score_total' => $total,
            'body' => $body,
        ]);

        $this->audit->log('tasting.published', 'review', $reviewId, $curatorId, [
            'lot_id' => $lotId,
            'score_total' => $total,
        ]);
        $this->notifications->notify(
            (int) $lot['artisan_id'],
            'tasting.received',
            'Cata destacada del comisario',
            'Sobre el lote ' . $lot['code'] . ' · ' . ($total ?? '—') . '/10',
            '/lots/' . $lotId
        );
        return $reviewId;
    }

    public function report(int $reporterId, array $data): int
    {
        if (!in_array($data['entity_type'] ?? '', ['recipe', 'lot', 'listing', 'review', 'user'], true)) {
            throw new ValidationException(['entity_type' => 'Tipo de entidad no válido.']);
        }
        $reportId = $this->moderation->createReport([
            'reporter_id' => $reporterId,
            'entity_type' => $data['entity_type'],
            'entity_id' => (int) ($data['entity_id'] ?? 0),
            'reason' => (string) ($data['reason'] ?? 'Sin motivo indicado'),
            'details' => trim((string) ($data['details'] ?? '')),
        ]);
        $this->audit->log('report.created', 'moderation_report', $reportId, $reporterId, [
            'entity_type' => $data['entity_type'],
            'entity_id' => (int) ($data['entity_id'] ?? 0),
        ]);
        return $reportId;
    }

    public function commissionDetail(int $commissionId, int $viewerId): array
    {
        $commission = $this->commissions->find($commissionId);
        if ($commission === null) {
            throw HttpException::notFound('La comisión no existe.');
        }
        if ((int) $commission['venue_id'] !== $viewerId && (int) $commission['artisan_id'] !== $viewerId && !AuthService::hasRole('admin')) {
            throw HttpException::forbidden('No participas en esa comisión.');
        }
        return [
            'commission' => $commission,
            'consignment' => $commission['consignment_id'] ? $this->commissions->consignment($commissionId) : null,
            'invoice' => $this->commissions->invoice($commissionId),
            'reviews' => $this->commissions->reviews($commissionId),
        ];
    }

    public function venueCommissions(int $venueId): array
    {
        return $this->commissions->forVenue($venueId);
    }

    public function artisanCommissions(int $artisanId): array
    {
        return $this->commissions->forArtisan($artisanId);
    }

    public function pendingReviews(int $venueId): array
    {
        return $this->commissions->pendingReviewsForVenue($venueId);
    }

    public function stats(): array
    {
        return [
            'requests' => $this->requests->stats(),
            'commissions' => $this->commissions->stats(),
        ];
    }
}
