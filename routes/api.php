<?php
declare(strict_types=1);

use App\Core\Router;
use App\Http\Api\AuthApiController;
use App\Http\Api\CalculatorApiController;
use App\Http\Api\DocsApiController;
use App\Http\Api\LotApiController;
use App\Http\Api\MarketplaceApiController;
use App\Http\Api\RecipeApiController;

/** @var Router $router */

$router->post('/api/v1/auth/token', [AuthApiController::class, 'token'], [
    'tags' => ['Autenticación'],
    'summary' => 'Obtiene un token Bearer a partir de correo y contraseña.',
    'public' => true,
]);
$router->get('/api/v1/me', [AuthApiController::class, 'me'], [
    'tags' => ['Autenticación'],
    'summary' => 'Devuelve la cuenta autenticada y sus roles.',
    'middleware' => ['api_auth'],
]);

$router->get('/api/v1/recipes', [RecipeApiController::class, 'index'], [
    'tags' => ['Recetas'],
    'summary' => 'Lista el catálogo público de recetas con filtros y paginación.',
    'public' => true,
]);
$router->get('/api/v1/recipes/{slug}', [RecipeApiController::class, 'show'], [
    'tags' => ['Recetas'],
    'summary' => 'Detalle de una receta, sus versiones y su cálculo vigente.',
    'public' => true,
]);
$router->post('/api/v1/recipes', [RecipeApiController::class, 'store'], [
    'tags' => ['Recetas'],
    'summary' => 'Publica una receta nueva (artesano).',
    'middleware' => ['api_auth'],
    'roles' => ['artesano'],
]);
$router->post('/api/v1/recipes/{id}/fork', [RecipeApiController::class, 'fork'], [
    'tags' => ['Recetas'],
    'summary' => 'Bifurca una receta pública en una copia propia.',
    'middleware' => ['api_auth'],
    'roles' => ['artesano'],
]);
$router->post('/api/v1/recipes/{id}/versions', [RecipeApiController::class, 'storeVersion'], [
    'tags' => ['Recetas'],
    'summary' => 'Añade una versión inmutable a una receta propia.',
    'middleware' => ['api_auth'],
    'roles' => ['artesano'],
]);
$router->get('/api/v1/recipes/{id}/scale', [RecipeApiController::class, 'scale'], [
    'tags' => ['Recetas'],
    'summary' => 'Escala una receta a un factor de producción.',
    'public' => true,
]);

$router->get('/api/v1/lots', [LotApiController::class, 'index'], [
    'tags' => ['Lotes'],
    'summary' => 'Lista los lotes públicos con su coherencia y unidades disponibles.',
    'public' => true,
]);
$router->get('/api/v1/lots/{id}', [LotApiController::class, 'show'], [
    'tags' => ['Lotes'],
    'summary' => 'Detalle de un lote con lecturas, veredicto y valoraciones.',
    'public' => true,
]);
$router->post('/api/v1/lots', [LotApiController::class, 'store'], [
    'tags' => ['Lotes'],
    'summary' => 'Registra un lote y su primera lectura física.',
    'middleware' => ['api_auth'],
    'roles' => ['artesano'],
]);
$router->post('/api/v1/lots/{id}/readings', [LotApiController::class, 'storeReading'], [
    'tags' => ['Lotes'],
    'summary' => 'Registra una lectura y recalcula la coherencia.',
    'middleware' => ['api_auth'],
    'roles' => ['artesano'],
]);
$router->get('/api/v1/lots/{id}/verdict', [LotApiController::class, 'verdict'], [
    'tags' => ['Lotes'],
    'summary' => 'Devuelve el último veredicto de coherencia con su desglose.',
    'public' => true,
]);

$router->get('/api/v1/requests', [MarketplaceApiController::class, 'requests'], [
    'tags' => ['Mercado'],
    'summary' => 'Lista las peticiones abiertas de los locales.',
    'public' => true,
]);
$router->post('/api/v1/requests', [MarketplaceApiController::class, 'storeRequest'], [
    'tags' => ['Mercado'],
    'summary' => 'Abre una petición (local con licencia verificada).',
    'middleware' => ['api_auth'],
    'roles' => ['local'],
]);
$router->post('/api/v1/requests/{id}/proposals', [MarketplaceApiController::class, 'propose'], [
    'tags' => ['Mercado'],
    'summary' => 'Propone un lote propio a una petición abierta.',
    'middleware' => ['api_auth'],
    'roles' => ['artesano'],
]);
$router->post('/api/v1/proposals/{id}/accept', [MarketplaceApiController::class, 'accept'], [
    'tags' => ['Mercado'],
    'summary' => 'Acepta una propuesta: reserva unidades y crea la comisión.',
    'middleware' => ['api_auth'],
    'roles' => ['local'],
]);
$router->get('/api/v1/commissions/{id}', [MarketplaceApiController::class, 'commission'], [
    'tags' => ['Mercado'],
    'summary' => 'Detalle de una comisión, su consignación, factura y valoraciones.',
    'middleware' => ['api_auth'],
]);
$router->post('/api/v1/commissions/{id}/complete', [MarketplaceApiController::class, 'completeDelivery'], [
    'tags' => ['Mercado'],
    'summary' => 'El local confirma la entrega de una venta; la comisión se completa.',
    'middleware' => ['api_auth'],
    'roles' => ['local'],
]);
$router->post('/api/v1/commissions/{id}/reviews', [MarketplaceApiController::class, 'storeReview'], [
    'tags' => ['Mercado'],
    'summary' => 'Valoración verificada del local (72 h de reflexión).',
    'middleware' => ['api_auth'],
    'roles' => ['local'],
]);
$router->get('/api/v1/reviews/pending', [MarketplaceApiController::class, 'pendingReviews'], [
    'tags' => ['Mercado'],
    'summary' => 'Comisiones pendientes de valoración por el local.',
    'middleware' => ['api_auth'],
    'roles' => ['local'],
]);
$router->post('/api/v1/consignments/{id}/close', [MarketplaceApiController::class, 'closeConsignment'], [
    'tags' => ['Mercado'],
    'summary' => 'Cierra una consignación y emite la factura de lo vendido.',
    'middleware' => ['api_auth'],
]);
$router->post('/api/v1/reports', [MarketplaceApiController::class, 'report'], [
    'tags' => ['Moderación'],
    'summary' => 'Denuncia una receta, lote, anuncio, valoración o usuario.',
    'middleware' => ['api_auth'],
]);

$router->post('/api/v1/calculator', [CalculatorApiController::class, 'calculate'], [
    'tags' => ['Calculadora'],
    'summary' => 'Ejecuta el motor de recetas y devuelve física, frascos y cronograma.',
    'public' => true,
]);
$router->get('/api/v1/calculator/presets', [CalculatorApiController::class, 'presets'], [
    'tags' => ['Calculadora'],
    'summary' => 'Catálogo de bases, extracciones y almíbares disponibles.',
    'public' => true,
]);

$router->get('/api/v1/openapi.json', [DocsApiController::class, 'openapi'], [
    'tags' => ['Sistema'],
    'summary' => 'Documento OpenAPI 3.1 generado desde las rutas.',
    'public' => true,
]);
$router->get('/api/v1/health', [DocsApiController::class, 'health'], [
    'tags' => ['Sistema'],
    'summary' => 'Comprobación de salud de la base de datos y del motor.',
    'public' => true,
]);
