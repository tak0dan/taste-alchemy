<?php
declare(strict_types=1);

use App\Core\Router;
use App\Http\Web\AccountController;
use App\Http\Web\AdminController;
use App\Http\Web\ArtisanController;
use App\Http\Web\AuthController;
use App\Http\Web\BarController;
use App\Http\Web\CalculatorController;
use App\Http\Web\CommissionController;
use App\Http\Web\CuratorController;
use App\Http\Web\HomeController;
use App\Http\Web\LotController;
use App\Http\Web\MarketplaceController;
use App\Http\Web\ModerationController;
use App\Http\Web\RecipeController;
use App\Http\Web\ReportController;
use App\Http\Web\WorkshopController;

/** @var Router $router */

// --- Público -----------------------------------------------------------------
$router->get('/', [HomeController::class, 'index']);
$router->get('/manifesto', [HomeController::class, 'manifesto']);

$router->get('/recipes', [RecipeController::class, 'index']);
$router->get('/recipes/{slug}', [RecipeController::class, 'show']);
$router->get('/recipes/{id}/scale', [RecipeController::class, 'scale'], ['middleware' => ['auth']]);
$router->get('/calculator', [CalculatorController::class, 'index']);
$router->post('/calculator', [CalculatorController::class, 'calculate']);

$router->get('/lots', [LotController::class, 'index']);
$router->get('/lots/{id}', [LotController::class, 'show']);

$router->get('/marketplace', [MarketplaceController::class, 'index']);
$router->get('/requests/{id}', [MarketplaceController::class, 'requestShow']);

$router->get('/artisans', [ArtisanController::class, 'index']);
$router->get('/artisans/{slug}', [ArtisanController::class, 'show']);

// --- Autenticación -----------------------------------------------------------
$router->get('/login', [AuthController::class, 'showLogin'], ['middleware' => ['guest']]);
$router->post('/login', [AuthController::class, 'login'], ['middleware' => ['guest', 'csrf']]);
$router->get('/register', [AuthController::class, 'showRegister'], ['middleware' => ['guest']]);
$router->post('/register', [AuthController::class, 'register'], ['middleware' => ['guest', 'csrf']]);
$router->post('/logout', [AuthController::class, 'logout'], ['middleware' => ['auth', 'csrf']]);

// --- Taller del artesano -----------------------------------------------------
$router->get('/workshop', [WorkshopController::class, 'index'], ['roles' => ['artesano']]);
$router->get('/workshop/recipes/new', [RecipeController::class, 'create'], ['roles' => ['artesano']]);
$router->post('/workshop/recipes', [RecipeController::class, 'store'], ['roles' => ['artesano'], 'middleware' => ['csrf']]);
$router->post('/recipes/{id}/fork', [RecipeController::class, 'fork'], ['roles' => ['artesano'], 'middleware' => ['csrf']]);
$router->post('/recipes/{id}/versions', [RecipeController::class, 'storeVersion'], ['roles' => ['artesano'], 'middleware' => ['csrf']]);

$router->get('/workshop/lots/new', [WorkshopController::class, 'newLot'], ['roles' => ['artesano']]);
$router->post('/workshop/lots', [WorkshopController::class, 'storeLot'], ['roles' => ['artesano'], 'middleware' => ['csrf']]);
$router->get('/workshop/lots/{id}', [WorkshopController::class, 'lotShow'], ['roles' => ['artesano']]);
$router->post('/workshop/lots/{id}/readings', [WorkshopController::class, 'addReading'], ['roles' => ['artesano'], 'middleware' => ['csrf']]);
$router->post('/workshop/lots/{id}/listing', [WorkshopController::class, 'publishListing'], ['roles' => ['artesano'], 'middleware' => ['csrf']]);
$router->post('/workshop/lots/{id}/status', [WorkshopController::class, 'changeStatus'], ['roles' => ['artesano'], 'middleware' => ['csrf']]);
$router->get('/workshop/opportunities', [WorkshopController::class, 'opportunities'], ['roles' => ['artesano']]);
$router->get('/workshop/opportunities/{id}', [WorkshopController::class, 'opportunityShow'], ['roles' => ['artesano']]);
$router->post('/workshop/opportunities/{id}/proposals', [WorkshopController::class, 'propose'], ['roles' => ['artesano'], 'middleware' => ['csrf']]);
$router->get('/workshop/proposals', [WorkshopController::class, 'proposals'], ['roles' => ['artesano']]);
$router->post('/workshop/proposals/{id}/withdraw', [WorkshopController::class, 'withdrawProposal'], ['roles' => ['artesano'], 'middleware' => ['csrf']]);
$router->get('/workshop/commissions', [WorkshopController::class, 'commissions'], ['roles' => ['artesano']]);

// --- Bar / local -------------------------------------------------------------
$router->get('/bar', [BarController::class, 'index'], ['roles' => ['local']]);
$router->get('/bar/requests/new', [BarController::class, 'newRequest'], ['roles' => ['local'], 'middleware' => ['verified_venue']]);
$router->post('/bar/requests', [BarController::class, 'storeRequest'], ['roles' => ['local'], 'middleware' => ['verified_venue', 'csrf']]);
$router->get('/bar/requests/{id}', [BarController::class, 'requestShow'], ['roles' => ['local']]);
$router->post('/bar/proposals/{id}/accept', [BarController::class, 'acceptProposal'], ['roles' => ['local'], 'middleware' => ['csrf']]);
$router->get('/bar/commissions', [BarController::class, 'commissions'], ['roles' => ['local']]);
$router->get('/bar/reviews', [BarController::class, 'reviews'], ['roles' => ['local']]);
$router->get('/bar/reviews/{id}', [BarController::class, 'reviewForm'], ['roles' => ['local']]);
$router->post('/bar/reviews/{id}', [BarController::class, 'storeReview'], ['roles' => ['local'], 'middleware' => ['csrf']]);
$router->get('/bar/verification', [BarController::class, 'verification'], ['roles' => ['local']]);
$router->post('/bar/verification', [BarController::class, 'submitVerification'], ['roles' => ['local'], 'middleware' => ['csrf']]);

$router->get('/commissions/{id}', [CommissionController::class, 'show'], ['middleware' => ['auth']]);
$router->post('/commissions/{id}/consignment', [CommissionController::class, 'closeConsignment'], ['middleware' => ['auth', 'csrf']]);
$router->post('/commissions/{id}/complete', [CommissionController::class, 'complete'], ['middleware' => ['auth', 'csrf']]);

// --- Comisario ---------------------------------------------------------------
$router->get('/curator', [CuratorController::class, 'index'], ['roles' => ['comisario']]);
$router->get('/curator/lots/{id}', [CuratorController::class, 'show'], ['roles' => ['comisario']]);
$router->post('/curator/lots/{id}/tasting', [CuratorController::class, 'store'], ['roles' => ['comisario'], 'middleware' => ['csrf']]);

// --- Moderación --------------------------------------------------------------
$router->get('/moderation', [ModerationController::class, 'index'], ['roles' => ['moderador']]);
$router->post('/moderation/verifications/{id}/approve', [ModerationController::class, 'approve'], ['roles' => ['moderador'], 'middleware' => ['csrf']]);
$router->post('/moderation/verifications/{id}/reject', [ModerationController::class, 'reject'], ['roles' => ['moderador'], 'middleware' => ['csrf']]);
$router->post('/moderation/reports/{id}/resolve', [ModerationController::class, 'resolveReport'], ['roles' => ['moderador'], 'middleware' => ['csrf']]);
$router->post('/moderation/users/{id}/sanction', [ModerationController::class, 'sanction'], ['roles' => ['moderador'], 'middleware' => ['csrf']]);

// --- Administración ----------------------------------------------------------
$router->get('/admin', [AdminController::class, 'index'], ['roles' => ['admin']]);
$router->post('/admin/settings', [AdminController::class, 'updateSetting'], ['roles' => ['admin'], 'middleware' => ['csrf']]);
$router->post('/admin/users/{id}/roles', [AdminController::class, 'updateRoles'], ['roles' => ['admin'], 'middleware' => ['csrf']]);
$router->post('/admin/ingredients', [AdminController::class, 'storeIngredient'], ['roles' => ['admin'], 'middleware' => ['csrf']]);
$router->post('/admin/ingredients/{id}', [AdminController::class, 'updateIngredient'], ['roles' => ['admin'], 'middleware' => ['csrf']]);

// --- Cuenta y denuncias ------------------------------------------------------
$router->get('/account', [AccountController::class, 'show'], ['middleware' => ['auth']]);
$router->post('/account', [AccountController::class, 'update'], ['middleware' => ['auth', 'csrf']]);
$router->post('/account/password', [AccountController::class, 'password'], ['middleware' => ['auth', 'csrf']]);
$router->get('/account/export', [AccountController::class, 'export'], ['middleware' => ['auth']]);
$router->post('/account/delete', [AccountController::class, 'delete'], ['middleware' => ['auth', 'csrf']]);

$router->post('/report', [ReportController::class, 'store'], ['middleware' => ['auth', 'csrf']]);
