<?php
declare(strict_types=1);

namespace App\Http\Web;

use App\Core\Request;
use App\Core\Response;
use App\Http\Controller;
use App\Service\MarketplaceService;

final class ReportController extends Controller
{
    public function store(Request $request): Response
    {
        (new MarketplaceService())->report($this->userId(), $request->all());
        return $this->redirect(
            (string) $request->input('return_to', '/'),
            'info',
            'Denuncia registrada. Un moderador la revisará.'
        );
    }
}
