<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\AccountService;
use App\Service\PrivacyService;

final class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        $userId = $this->userId();

        return $this->view('account', [
            'title' => 'Tu cuenta',
            'account' => (new UserRepository())->find($userId),
            'artisan' => (new ProfileRepository())->artisan($userId),
            'venue' => (new ProfileRepository())->venue($userId),
        ]);
    }

    public function update(Request $request): Response
    {
        (new AccountService())->updateProfile($this->userId(), $request->all());
        return $this->redirect('/account', 'success', 'Perfil actualizado.');
    }

    public function password(Request $request): Response
    {
        (new AccountService())->changePassword(
            $this->userId(),
            (string) $request->input('current_password', ''),
            (string) $request->input('password', '')
        );
        return $this->redirect('/account', 'success', 'Contraseña cambiada.');
    }

    public function export(Request $request): Response
    {
        $data = (new PrivacyService())->export($this->userId());
        return Response::download(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}',
            'taste-alchemy-' . $this->userId() . '.json'
        );
    }

    public function delete(Request $request): Response
    {
        (new PrivacyService())->delete($this->userId());
        return $this->redirect('/', 'info', 'Cuenta anonimizada. Facturas y auditoría se conservan por obligación legal.');
    }
}
