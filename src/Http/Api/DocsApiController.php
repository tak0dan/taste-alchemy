<?php
declare(strict_types=1);

namespace App\Http\Api;

use App\Calculation\RecipeEngine;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Http\Controller;

final class DocsApiController extends Controller
{
    public function health(Request $request): Response
    {
        $db = false;
        $error = null;
        try {
            $db = (int) Database::value('SELECT 1') === 1;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $engine = RecipeEngine::calculate(['mode' => 'ingredientes', 'vodka_ml' => 500, 'ingredient_g' => 500]);

        return Response::json([
            'status' => $db ? 'ok' : 'degraded',
            'checks' => [
                'database' => $db,
                'engine' => $engine['volumes']['final_ml'] > 0,
            ],
            'engine_sample' => [
                'abv_pct' => $engine['abv_pct'],
                'brix' => $engine['brix'],
                'final_ml' => $engine['volumes']['final_ml'],
                'freezing_point_c' => $engine['freeze']['freezing_point_c'],
            ],
            'version' => (string) Config::get('APP_VERSION', '1.0.0'),
            'time' => date('c'),
            'error' => $error,
        ], $db ? 200 : 503);
    }

    public function openapi(Request $request): Response
    {
        return Response::json($this->document());
    }

    private function document(): array
    {
        $router = new Router();
        $routes = $this->collectRoutes($router);

        $paths = [];
        $tags = [];
        foreach ($routes as $route) {
            $path = $route['path'];
            if (!str_starts_with($path, '/api/v1')) {
                continue;
            }
            $options = $route['options'];
            $method = strtolower($route['method']);
            $public = (bool) ($options['public'] ?? false);
            $tag = $options['tags'][0] ?? 'General';
            $tags[$tag] = true;

            $operation = [
                'tags' => $options['tags'] ?? [$tag],
                'summary' => $options['summary'] ?? ($route['method'] . ' ' . $path),
                'operationId' => $this->operationId($route['method'], $path),
                'responses' => [
                    '200' => ['description' => 'Respuesta correcta'],
                    '401' => ['description' => 'No autenticado'],
                    '422' => ['description' => 'Validación o regla de negocio'],
                ],
            ];
            if (!$public) {
                $operation['security'] = [['bearerAuth' => []]];
            }
            if ($route['method'] === 'GET') {
                $query = $this->queryParameters($path);
                if ($query !== []) {
                    $operation['parameters'] = $query;
                }
            } else {
                $operation['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object', 'additionalProperties' => true],
                        ],
                    ],
                ];
                $operation['responses'] = [
                    '201' => ['description' => 'Recurso creado'],
                    '401' => ['description' => 'No autenticado'],
                    '422' => ['description' => 'Validación o regla de negocio'],
                ] + $operation['responses'];
            }
            if (preg_match_all('/\{([a-z_]+)\}/', $path, $m)) {
                $parameters = [];
                foreach ($m[1] as $name) {
                    $parameters[] = [
                        'name' => $name,
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ];
                }
                $operation['parameters'] = array_merge($operation['parameters'] ?? [], $parameters);
            }

            $paths[$path][$method] = $operation;
        }

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Taste Alchemy API',
                'version' => (string) Config::get('APP_VERSION', '1.0.0'),
                'description' => 'API REST del taller y el mercado de licores de élite. '
                    . 'Autenticación por token Bearer obtenido en /api/v1/auth/token. '
                    . 'Las reglas de negocio (coherencia, reserva de unidades, 72 h de reflexión) '
                    . 'se aplican en la base de datos y se devuelven como 422.',
                'contact' => ['name' => 'Taste Alchemy', 'url' => url('/manifesto')],
            ],
            'servers' => [['url' => rtrim((string) Config::get('APP_URL', ''), '/')]],
            'tags' => array_map(
                static fn(string $name): array => ['name' => $name],
                array_keys($tags)
            ),
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'HMAC-SHA256',
                    ],
                ],
            ],
            'paths' => $paths,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function collectRoutes(Router $router): array
    {
        $web = require BASE_PATH . '/routes/web.php';
        $api = require BASE_PATH . '/routes/api.php';
        return $router->routes();
    }

    private function operationId(string $method, string $path): string
    {
        $clean = trim(preg_replace('/[^a-zA-Z0-9]+/', ' ', $path) ?? '', ' ');
        $words = array_map('ucfirst', explode(' ', $clean));
        return strtolower($method) . implode('', $words);
    }

    /** @return array<int, array<string, mixed>> */
    private function queryParameters(string $path): array
    {
        $parameters = [];
        $common = [
            'page' => ['type' => 'integer', 'minimum' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            'q' => ['type' => 'string'],
            'territory' => ['type' => 'string'],
            'style' => ['type' => 'string'],
            'sort' => ['type' => 'string'],
            'min_coherence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            'factor' => ['type' => 'number', 'minimum' => 0.001, 'maximum' => 1000],
        ];
        if (str_contains($path, '/recipes')) {
            foreach (['q', 'style', 'territory', 'sort', 'page', 'per_page', 'factor'] as $name) {
                $parameters[] = ['name' => $name, 'in' => 'query', 'required' => false, 'schema' => $common[$name]];
            }
        } elseif (str_contains($path, '/lots')) {
            foreach (['q', 'territory', 'sort', 'page', 'per_page', 'min_coherence'] as $name) {
                $parameters[] = ['name' => $name, 'in' => 'query', 'required' => false, 'schema' => $common[$name]];
            }
        } elseif (str_contains($path, '/requests')) {
            foreach (['q', 'territory', 'style', 'limit'] as $name) {
                $parameters[] = ['name' => $name, 'in' => 'query', 'required' => false, 'schema' => $common[$name]];
            }
        }
        return $parameters;
    }
}
