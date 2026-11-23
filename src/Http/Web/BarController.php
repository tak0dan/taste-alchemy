<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repository\CommissionRepository;
use App\Repository\NotificationRepository;
use App\Repository\ProfileRepository;
use App\Repository\ProposalRepository;
use App\Repository\RequestRepository;
use App\Repository\ReviewRepository;
use App\Service\MarketplaceService;
use App\Service\ModerationService;

final class BarController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $this->userId();
        $requests = new RequestRepository();
        $market = new MarketplaceService();
        $commissions = $market->venueCommissions($userId);
        $profile = (new ProfileRepository())->venue($userId);

        return $this->view('bar/index', [
            'title' => 'Tu bar',
            'profile' => $profile,
            'requests' => $requests->byVenue($userId),
            'commissions' => array_slice($commissions, 0, 8),
            'pendingReviews' => $market->pendingReviews($userId),
            'notifications' => (new NotificationRepository())->forUser($userId, 8),
            'stats' => [
                'requests' => $requests->stats(),
                'commissions' => (new CommissionRepository())->stats(),
            ],
            'verified' => $profile !== null && $profile['license_status'] === 'verificada',
        ]);
    }

    public function newRequest(Request $request): Response
    {
        return $this->view('bar/request-new', [
            'title' => 'Abrir una petición',
            'profile' => (new ProfileRepository())->venue($this->userId()),
        ]);
    }

    public function storeRequest(Request $request): Response
    {
        $id = (new MarketplaceService())->createRequest($this->userId(), $request->all());

        return $this->redirect(
            '/bar/requests/' . $id,
            'success',
            'Petición abierta. Los artesanos ya pueden proponer lotes concretos.'
        );
    }

    public function requestShow(Request $request, array $params): Response
    {
        $requestId = (int) $params['id'];
        $market = new MarketplaceService();
        $detail = $market->requestDetail($requestId, $this->userId(), true);
        if ((int) $detail['request']['venue_id'] !== $this->userId()) {
            throw HttpException::forbidden('Esa petición pertenece a otro local.');
        }

        return $this->view('bar/request-show', [
            'title' => $detail['request']['title'],
            'detail' => $detail,
        ]);
    }

    public function acceptProposal(Request $request, array $params): Response
    {
        $mode = (string) $request->input('mode', 'venta');
        $commissionId = (new MarketplaceService())->accept($this->userId(), (int) $params['id'], $mode);

        return $this->redirect(
            '/commissions/' . $commissionId,
            'success',
            'Propuesta aceptada. Las unidades quedan reservadas y la comisión creada.'
        );
    }

    public function commissions(Request $request): Response
    {
        return $this->view('bar/commissions', [
            'title' => 'Tus comisiones',
            'commissions' => (new MarketplaceService())->venueCommissions($this->userId()),
        ]);
    }

    public function reviews(Request $request): Response
    {
        $market = new MarketplaceService();

        return $this->view('bar/reviews', [
            'title' => 'Valoraciones verificadas',
            'pending' => $market->pendingReviews($this->userId()),
            'written' => (new ReviewRepository())->byAuthor($this->userId()),
        ]);
    }

    public function reviewForm(Request $request, array $params): Response
    {
        $commissionId = (int) $params['id'];
        $detail = (new MarketplaceService())->commissionDetail($commissionId, $this->userId());
        if ((int) $detail['commission']['venue_id'] !== $this->userId()) {
            throw HttpException::forbidden('Sólo el local de la comisión puede valorarla.');
        }

        return $this->view('bar/review-form', [
            'title' => 'Valorar ' . $detail['commission']['lot_code'],
            'detail' => $detail,
        ]);
    }

    public function storeReview(Request $request, array $params): Response
    {
        (new MarketplaceService())->submitReview($this->userId(), (int) $params['id'], $request->all());
        return $this->redirect(
            '/bar/reviews',
            'success',
            'Valoración publicada. Está vinculada a una comisión real.'
        );
    }

    public function verification(Request $request): Response
    {
        $userId = $this->userId();
        $profile = (new ProfileRepository())->venue($userId);
        $moderationRepo = new \App\Repository\ModerationRepository();
        $latest = $moderationRepo->latestVerificationForVenue($userId);

        return $this->view('bar/verification', [
            'title' => 'Verificación de licencia',
            'profile' => $profile,
            'latest' => $latest,
        ]);
    }

    public function submitVerification(Request $request): Response
    {
        (new ModerationService())->submitVerification($this->userId(), $request->all());
        return $this->redirect(
            '/bar/verification',
            'info',
            'Documentación enviada. Un moderador la revisará manualmente.'
        );
    }
}
