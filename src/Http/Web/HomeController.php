<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repository\CatalogRepository;
use App\Repository\CommissionRepository;
use App\Repository\LotRepository;
use App\Repository\ProfileRepository;
use App\Repository\RecipeRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $recipes = new RecipeRepository();
        $lots = new LotRepository();
        $profiles = new ProfileRepository();
        $reviews = new ReviewRepository();
        $commissions = new CommissionRepository();
        $users = new UserRepository();
        $catalog = new CatalogRepository();

        return $this->view('home', [
            'title' => 'El taller y el mercado de los licores de élite',
            'stats' => [
                'recipes' => $recipes->stats(),
                'lots' => $lots->stats(),
                'commissions' => $commissions->stats(),
                'reviews' => $reviews->countPublished(),
                'members' => $users->countAll(),
            ],
            'featuredRecipes' => $recipes->recent(3),
            'featuredLots' => $lots->recent(3),
            'artisans' => array_slice($profiles->allArtisans(), 0, 4),
            'reviews' => $reviews->recent(3),
            'settings' => $catalog->settings(),
        ]);
    }

    public function manifesto(Request $request): Response
    {
        return $this->view('manifesto', [
            'title' => 'Manifiesto',
            'settings' => (new CatalogRepository())->settings(),
        ]);
    }
}
