<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Service\AdminService;

final class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin/index', [
            'title' => 'Administración',
            'dashboard' => (new AdminService())->dashboard(),
            'statistics' => (new AdminService())->statistics(),
        ]);
    }

    public function updateSetting(Request $request): Response
    {
        $key = (string) $request->input('setting_key', '');
        $value = trim((string) $request->input('setting_value', ''));
        (new AdminService())->updateSetting($this->userId(), $key, $value);
        return $this->redirect('/admin', 'success', 'Parámetro actualizado: ' . $key . ' = ' . $value . '.');
    }

    public function updateRoles(Request $request, array $params): Response
    {
        $roles = $request->input('roles', []);
        (new AdminService())->updateRoles($this->userId(), (int) $params['id'], is_array($roles) ? $roles : []);
        return $this->redirect('/admin', 'success', 'Roles actualizados.');
    }

    public function storeIngredient(Request $request): Response
    {
        (new AdminService())->createIngredient($this->userId(), $request->all());
        return $this->redirect('/admin', 'success', 'Ingrediente añadido al catálogo.');
    }

    public function updateIngredient(Request $request, array $params): Response
    {
        (new AdminService())->updateIngredient($this->userId(), (int) $params['id'], $request->all());
        return $this->redirect('/admin', 'success', 'Ingrediente actualizado.');
    }
}
