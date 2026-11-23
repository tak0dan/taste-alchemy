<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repository\CatalogRepository;
use App\Repository\LotRepository;
use App\Repository\NotificationRepository;
use App\Repository\ProfileRepository;
use App\Repository\ProposalRepository;
use App\Repository\RecipeRepository;
use App\Service\AuthService;
use App\Service\LotService;
use App\Service\MarketplaceService;
use App\Service\RecipeService;

final class WorkshopController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $this->userId();
        $lots = new LotRepository();
        $recipes = new RecipeRepository();
        $proposals = new ProposalRepository();
        $notifications = new NotificationRepository();
        $market = new MarketplaceService();

        $commissions = $market->artisanCommissions($userId);

        return $this->view('workshop/index', [
            'title' => 'Tu taller',
            'profile' => (new ProfileRepository())->artisan($userId),
            'recipes' => $recipes->byAuthor($userId),
            'lots' => $lots->byArtisan($userId),
            'listings' => $lots->listings($userId),
            'commissions' => array_slice($commissions, 0, 6),
            'pendingProposals' => $proposals->countPendingForArtisan($userId),
            'notifications' => $notifications->forUser($userId, 8),
            'openRequests' => count($market->openRequests([], 50)),
            'stats' => [
                'lots' => $lots->stats(),
                'recipes' => $recipes->stats(),
            ],
        ]);
    }

    public function newLot(Request $request): Response
    {
        $recipes = (new RecipeRepository())->byAuthor($this->userId());

        return $this->view('workshop/lot-new', [
            'title' => 'Registrar lote',
            'recipes' => $recipes,
        ]);
    }

    public function storeLot(Request $request): Response
    {
        $service = new LotService();
        $lotId = $service->create($this->userId(), $request->all());

        return $this->redirect(
            '/workshop/lots/' . $lotId,
            'success',
            'Lote registrado con su primera lectura. La coherencia ya está calculada.'
        );
    }

    public function lotShow(Request $request, array $params): Response
    {
        $lotId = (int) $params['id'];
        $service = new LotService();
        $detail = $service->detail($lotId);
        if ((int) $detail['lot']['artisan_id'] !== $this->userId()) {
            throw HttpException::forbidden('Ese lote pertenece a otro taller.');
        }

        return $this->view('workshop/lot-show', [
            'title' => 'Lote ' . $detail['lot']['code'],
            'detail' => $detail,
            'listing' => $detail['listing'],
            'canPublish' => (int) $detail['lot']['coherence_score'] >= 70,
            'minScore' => (new CatalogRepository())->threshold('trust.listing_min_score', 70),
        ]);
    }

    public function addReading(Request $request, array $params): Response
    {
        $result = (new LotService())->addReading($this->userId(), (int) $params['id'], $request->all());
        return $this->redirect(
            '/workshop/lots/' . (int) $params['id'],
            $result['score'] >= 70 ? 'success' : 'warning',
            'Lectura registrada. Coherencia actual: ' . $result['score'] . '/100.'
        );
    }

    public function publishListing(Request $request, array $params): Response
    {
        (new LotService())->publishListing($this->userId(), (int) $params['id'], $request->all());
        return $this->redirect(
            '/workshop/lots/' . (int) $params['id'],
            'success',
            'Anuncio publicado en el mercado.'
        );
    }

    public function changeStatus(Request $request, array $params): Response
    {
        $status = (string) $request->input('status', '');
        (new LotService())->changeStatus($this->userId(), (int) $params['id'], $status);
        return $this->redirect(
            '/workshop/lots/' . (int) $params['id'],
            'success',
            'Estado del lote actualizado a ' . status_label($status) . '.'
        );
    }

    public function opportunities(Request $request): Response
    {
        $market = new MarketplaceService();
        $filters = [
            'territory' => trim((string) $request->query('territory', '')),
            'style' => (string) $request->query('style', ''),
            'q' => trim((string) $request->query('q', '')),
            'abv' => $request->query('abv', ''),
        ];

        return $this->view('workshop/opportunities', [
            'title' => 'Peticiones abiertas',
            'requests' => $market->openRequests($filters, 50),
            'filters' => $filters,
            'myLots' => (new LotRepository())->byArtisan($this->userId()),
        ]);
    }

    public function opportunityShow(Request $request, array $params): Response
    {
        $market = new MarketplaceService();
        $detail = $market->requestDetail((int) $params['id'], $this->userId(), false);

        return $this->view('workshop/opportunity-show', [
            'title' => $detail['request']['title'],
            'detail' => $detail,
            'myLots' => (new LotRepository())->byArtisan($this->userId()),
            'minScore' => (new CatalogRepository())->threshold('trust.listing_min_score', 70),
        ]);
    }

    public function propose(Request $request, array $params): Response
    {
        (new MarketplaceService())->propose($this->userId(), (int) $params['id'], $request->all());
        return $this->redirect(
            '/workshop/opportunities/' . (int) $params['id'],
            'success',
            'Propuesta enviada. El local comparará lotes con evidencia, no promesas.'
        );
    }

    public function proposals(Request $request): Response
    {
        $proposals = new ProposalRepository();

        return $this->view('workshop/proposals', [
            'title' => 'Tus propuestas',
            'proposals' => $proposals->forArtisan($this->userId()),
        ]);
    }

    public function withdrawProposal(Request $request, array $params): Response
    {
        (new MarketplaceService())->withdrawProposal($this->userId(), (int) $params['id']);
        return $this->redirect('/workshop/proposals', 'info', 'Propuesta retirada.');
    }

    public function commissions(Request $request): Response
    {
        return $this->view('workshop/commissions', [
            'title' => 'Tus comisiones',
            'commissions' => (new MarketplaceService())->artisanCommissions($this->userId()),
        ]);
    }
}
