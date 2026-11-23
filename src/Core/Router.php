<?php
declare(strict_types=1);

namespace App\Core;

use App\Service\AuthService;

/**
 * Router con middleware declarativo y metadatos de ruta (de los que se genera
 * la documentación OpenAPI que sirve /api/v1/openapi.json).
 */
final class Router
{
    /** @var array<int, array<string, mixed>> */
    private array $routes = [];
    private string $basePath = '';

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function add(string $method, string $path, callable|array $handler, array $options = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'options' => $options,
        ];
    }

    public function get(string $path, callable|array $handler, array $options = []): void
    {
        $this->add('GET', $path, $handler, $options);
    }

    public function post(string $path, callable|array $handler, array $options = []): void
    {
        $this->add('POST', $path, $handler, $options);
    }

    public function put(string $path, callable|array $handler, array $options = []): void
    {
        $this->add('PUT', $path, $handler, $options);
    }

    public function delete(string $path, callable|array $handler, array $options = []): void
    {
        $this->add('DELETE', $path, $handler, $options);
    }

    /** @return array<int, array<string, mixed>> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
    {
        AuthService::boot($request);
        $path = $request->path();
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath)) ?: '/';
        }

        $allowed = [];
        foreach ($this->routes as $route) {
            $params = $this->match($route['path'], $path);
            if ($params === null) {
                continue;
            }
            if ($route['method'] !== $request->method()) {
                $allowed[$route['method']] = true;
                continue;
            }
            return $this->run($route, $request, $params);
        }

        if ($allowed !== []) {
            return $this->error(new HttpException(405, 'Método no permitido'), $request, array_keys($allowed));
        }
        return $this->error(HttpException::notFound('La página que buscas no existe.'), $request);
    }

    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn(array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $pattern
        );
        $regex = '#^' . $regex . '$#';
        if (!preg_match($regex, $path, $matches)) {
            return null;
        }
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = urldecode($value);
            }
        }
        return $params;
    }

    private function run(array $route, Request $request, array $params): Response
    {
        $options = $route['options'];
        try {
            $this->runMiddleware($options, $request);

            [$class, $method] = $route['handler'];
            $controller = new $class();
            $result = $controller->{$method}($request, $params);

            if ($result instanceof Response) {
                return $result;
            }
            if (is_array($result)) {
                return Response::json($result);
            }
            return Response::html((string) $result);
        } catch (ValidationException $e) {
            return $this->validationFailure($e, $request);
        } catch (HttpException $e) {
            return $this->error($e, $request);
        } catch (\PDOException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'SQLSTATE[45000]')) {
                $clean = $this->cleanDatabaseMessage($message);
                if ($request->isApi()) {
                    return Response::json([
                        'error' => 'regla_de_negocio',
                        'message' => $clean,
                    ], 422);
                }
                Session::flash('error', $clean);
                return Response::back('/');
            }
            throw $e;
        } catch (\Throwable $e) {
            if (\App\Core\Config::bool('APP_DEBUG', false)) {
                $body = '<h1>Error ' . 500 . '</h1><pre>' . e($e->getMessage()) . '</pre><pre>'
                    . e($e->getTraceAsString()) . '</pre>';
                return Response::html('<div style="font-family:monospace;padding:2rem">' . $body . '</div>', 500);
            }
            error_log((string) $e);
            return Response::html('<h1>Error interno</h1>', 500);
        }
    }

    private function runMiddleware(array $options, Request $request): void
    {
        $middleware = $options['middleware'] ?? [];
        $roles = $options['roles'] ?? [];

        if (in_array('guest', $middleware, true) && AuthService::check()) {
            throw new HttpException(302, 'Ya has iniciado sesión', '/');
        }

        if (in_array('auth', $middleware, true) || in_array('api_auth', $middleware, true) || $roles !== []) {
            if (!AuthService::check()) {
                if ($request->isApi()) {
                    throw HttpException::unauthorized('Autenticación requerida. Usa un token Bearer o una sesión.');
                }
                Session::flash('info', 'Inicia sesión para continuar.');
                throw new HttpException(302, 'Inicia sesión', '/login?next=' . urlencode($request->path()));
            }
        }

        if ($roles !== [] && !AuthService::hasAnyRole($roles)) {
            if ($request->isApi()) {
                throw HttpException::forbidden('Tu cuenta no tiene el rol necesario: ' . implode(', ', $roles));
            }
            throw HttpException::forbidden('Tu cuenta no tiene permiso para entrar en esta sección.');
        }

        if (in_array('verified_venue', $middleware, true)) {
            $user = AuthService::user();
            if (!$user || !AuthService::isVerifiedVenue()) {
                if ($request->isApi()) {
                    throw HttpException::forbidden('El local debe tener la licencia verificada.');
                }
                Session::flash('info', 'La licencia de tu local está pendiente de verificación.');
                throw new HttpException(302, 'Licencia pendiente', '/bar/verification');
            }
        }

        if (in_array('csrf', $middleware, true) && $request->isPost() && $request->bearerToken() === null) {
            $token = $request->input('_token') ?? $request->header('x-csrf-token');
            Csrf::verify($token);
        }
    }

    private function validationFailure(ValidationException $e, Request $request): Response
    {
        if ($request->isApi()) {
            return Response::json([
                'error' => 'validacion',
                'message' => $e->getMessage(),
                'errors' => $e->errors,
            ], 422);
        }
        Session::rememberOld($request->all());
        Session::flash('error', implode(' ', $e->errors));
        return Response::back('/');
    }

    private function error(HttpException $e, Request $request, array $allowed = []): Response
    {
        if ($e->status === 302) {
            if ($request->isApi()) {
                return Response::json(['error' => 'redireccion', 'message' => $e->getMessage(), 'location' => $e->redirectTo], 302);
            }
            return Response::redirect(url($e->redirectTo ?? '/'));
        }

        if ($request->isApi()) {
            $payload = ['error' => $this->errorCode($e->status), 'message' => $e->getMessage()];
            if ($e->payload !== []) {
                $payload = array_merge($payload, $e->payload);
            }
            $headers = $allowed !== [] ? ['Allow' => implode(', ', $allowed)] : [];
            return Response::json($payload, $e->status, $headers);
        }

        $view = in_array($e->status, [404, 403, 419, 500], true) ? 'errors/' . $e->status : 'errors/generic';
        $body = View::render($view, [
            'title' => 'Error ' . $e->status,
            'status' => $e->status,
            'message' => $e->getMessage(),
        ]);
        return Response::html($body, $e->status);
    }

    private function errorCode(int $status): string
    {
        return match ($status) {
            400 => 'peticion_invalida',
            401 => 'no_autenticado',
            403 => 'prohibido',
            404 => 'no_encontrado',
            405 => 'metodo_no_permitido',
            409 => 'conflicto',
            419 => 'csrf',
            422 => 'validacion',
            default => 'error',
        };
    }

    private function cleanDatabaseMessage(string $message): string
    {
        if (preg_match('/MESSAGE_TEXT[^:]*: ([^\']+)/', $message, $m)) {
            $message = $m[1];
        }
        if (preg_match('/([A-Z_]+):\s*(.+)$/s', $message, $m)) {
            $labels = [
                'COHERENCIA_INSUFICIENTE' => 'El lote no alcanza el umbral de coherencia',
                'UNIDADES_EXCEDIDAS' => 'No hay unidades suficientes en el lote',
                'SIN_UNIDADES' => 'No hay unidades suficientes en el lote',
                'LICENCIA_NO_VERIFICADA' => 'El local no tiene la licencia verificada',
                'VALORACION_NO_VERIFICADA' => 'La valoración no es verificable',
                'VALORACION_PREMATURA' => 'Deben pasar 72 horas antes de valorar',
                'AUDIT_INMUTABLE' => 'La traza de auditoría es inmutable',
                'PROPUESTA_NO_DISPONIBLE' => 'La propuesta ya no está disponible',
                'PETICION_NO_ABIERTA' => 'La petición ya no está abierta',
                'CUENTAS_INCOHERENTES' => 'Las cuentas no cuadran',
                'NIF_FALTANTE' => 'Falta el NIF de alguno de los operadores',
                'NO_AUTORIZADO' => 'No tienes permiso sobre esta petición',
                'MODO_NO_OFRECIDO' => 'El artesano no ofreció ese modo de venta',
                'CONSIGNACION_NO_ABIERTA' => 'La consignación ya está cerrada',
                'LOTE_NO_DISPONIBLE' => 'El lote no está disponible para servir',
                'LOTE_NO_ACTIVO' => 'El lote no admite lecturas',
                'FECHA_INVALIDA' => 'La fecha de la lectura no es válida',
                'FECHA_FUTURA' => 'No se pueden registrar lecturas futuras',
            ];
            $code = $m[1];
            return ($labels[$code] ?? 'No se pudo completar la operación') . ': ' . trim($m[2]);
        }
        return 'No se pudo completar la operación.';
    }
}
