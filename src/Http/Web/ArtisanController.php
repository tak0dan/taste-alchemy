<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repository\ProfileRepository;
use App\Repository\RecipeRepository;

final class ArtisanController extends Controller
{
    public function index(Request $request): Response
    {
        $profiles = new ProfileRepository();

        return $this->view('artisans/index', [
            'title' => 'Talleres',
            'artisans' => $profiles->allArtisans(),
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $profiles = new ProfileRepository();
        $artisan = $profiles->artisanBySlug((string) $params['slug']);
        if ($artisan === null) {
            throw HttpException::notFound('Ese taller no existe.');
        }

        return $this->view('artisans/show', [
            'title' => $artisan['studio_name'],
            'portfolio' => $profiles->publicArtisanPortfolio((int) $artisan['user_id']),
        ]);
    }
}
