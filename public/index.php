<?php
declare(strict_types=1);

/**
 * Punto de entrada único. Todas las peticiones pasan por aquí: el router
 * resuelve la ruta, ejecuta el middleware y devuelve una Response.
 */
define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/autoload.php';

use App\Core\Config;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

Config::load();
date_default_timezone_set((string) Config::get('APP_TIMEZONE', 'Europe/Madrid'));
Session::start();

$router = new Router((string) Config::get('APP_BASE_PATH', ''));
require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';

$request = Request::capture();
$response = $router->dispatch($request);
$response->send();

if ($request->method() === 'GET') {
    Session::clearOld();
}
