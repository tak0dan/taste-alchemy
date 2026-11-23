<?php
declare(strict_types=1);

namespace App\Http\Api;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repository\UserRepository;
use App\Service\AuthService;

final class AuthApiController extends Controller
{
    public function token(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $user = AuthService::attempt($email, $password);

        if ($user === null) {
            return Response::json([
                'error' => 'credenciales_invalidas',
                'message' => 'Correo o contraseña incorrectos, o la cuenta está suspendida.',
            ], 401);
        }

        $ttl = (int) $request->input('ttl', Config::int('TOKEN_TTL', 86400));
        $ttl = max(300, min(604800, $ttl));
        $token = AuthService::issueToken((int) $user['id'], $ttl);

        return Response::json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => $ttl,
            'user' => [
                'id' => (int) $user['id'],
                'display_name' => $user['display_name'],
                'email' => $user['email'],
                'roles' => $user['roles'],
            ],
        ]);
    }

    public function me(Request $request): Response
    {
        $user = (new UserRepository())->find($this->userId());
        if ($user === null) {
            return Response::json(['error' => 'no_encontrado'], 404);
        }
        unset($user['password_hash']);

        return Response::json(['data' => $user]);
    }
}
