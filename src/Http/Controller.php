<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Service\AuthService;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        return Response::html(\App\Core\View::render($template, $data, $layout));
    }

    protected function redirect(string $to, string $type = 'success', ?string $message = null): Response
    {
        if ($message !== null) {
            Session::flash($type, $message);
        }
        return Response::redirect(url($to));
    }

    protected function user(): array
    {
        $user = AuthService::user();
        if ($user === null) {
            throw HttpException::unauthorized();
        }
        return $user;
    }

    protected function userId(): int
    {
        return (int) $this->user()['id'];
    }

    protected function requireRole(string $role): void
    {
        if (!AuthService::hasRole($role)) {
            throw HttpException::forbidden('Tu cuenta no tiene el rol de ' . $role . '.');
        }
    }

    protected function intParam(array $params, string $key = 'id'): int
    {
        return (int) ($params[$key] ?? 0);
    }
}
