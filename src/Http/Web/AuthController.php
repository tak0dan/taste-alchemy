<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Http\Controller;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\AccountService;
use App\Service\AuthService;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', [
            'title' => 'Entrar',
            'next' => (string) $request->query('next', ''),
        ]);
    }

    public function login(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $user = AuthService::attempt($email, $password);

        if ($user === null) {
            Session::rememberOld(['email' => $email]);
            Session::flash('error', 'Correo o contraseña incorrectos, o la cuenta está suspendida.');
            return Response::redirect(url('/login'));
        }

        AuthService::login($user);
        $next = (string) $request->input('next', '');
        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
            $next = AuthService::homeFor($user);
        }

        return $this->redirect($next, 'success', 'Bienvenido de nuevo, ' . $user['display_name'] . '.');
    }

    public function showRegister(Request $request): Response
    {
        return $this->view('auth/register', [
            'title' => 'Abrir cuenta',
            'kind' => (string) $request->query('kind', 'artesano'),
        ]);
    }

    public function register(Request $request): Response
    {
        $kind = $request->input('kind', 'artesano') === 'local' ? 'local' : 'artesano';
        $service = new AccountService();
        $userId = $kind === 'local'
            ? $service->registerVenue($request->all())
            : $service->registerArtisan($request->all());

        $user = (new UserRepository())->find($userId);
        if ($user === null) {
            throw new HttpException(500, 'No se pudo crear la cuenta.');
        }
        AuthService::login($user);

        if ($kind === 'local') {
            return $this->redirect(
                '/bar/verification',
                'info',
                'Cuenta creada. Sube tu licencia para que un moderador la verifique.'
            );
        }

        return $this->redirect(
            '/workshop',
            'success',
            'Tu taller ya existe. Publica una receta y registra tu primer lote.'
        );
    }

    public function logout(Request $request): Response
    {
        AuthService::logout();
        return $this->redirect('/', 'info', 'Has cerrado sesión.');
    }
}
