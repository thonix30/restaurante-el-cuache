<?php
namespace App\Controllers;

use App\Attributes\Authorize;
use App\Attributes\Route;
use App\Core\Controller;
use App\DTOs\LoginDTO;
use App\Services\AuthService;
use App\Services\SyncService;

class AuthController extends Controller
{
    #[Route('/auth/login', 'POST')]
    public function login()
    {
        $this->ok((new AuthService())->login(LoginDTO::fromRequest($this->input())), 'Bienvenido');
    }

    #[Route('/auth/me', 'GET')]
    #[Authorize]
    public function me()
    {
        $this->ok((new AuthService())->me());
    }

    #[Route('/auth/logout', 'POST')]
    public function logout()
    {
        (new AuthService())->logout();
        $this->ok(null, 'Sesión cerrada');
    }

    /** Sondeo en vivo del personal (avisos, comandas listas, agotados). */
    #[Route('/sync', 'GET')]
    #[Authorize]
    public function sync()
    {
        $this->ok((new SyncService())->estado());
    }
}
