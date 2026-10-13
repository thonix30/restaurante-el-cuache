<?php
namespace App\Controllers;

use App\Attributes\Authorize;
use App\Attributes\PublicRoute;
use App\Attributes\Route;
use App\Core\Controller;
use App\DTOs\SolicitudDTO;
use App\Repositories\EmpleadoRepository;
use App\Services\AsistenciaService;
use App\Services\PerfilService;
use App\Services\SolicitudService;
use App\Utils\Auth;

/**
 * Para todo el equipo:
 *  - Kiosco de marcación (tablet en la entrada): identifica con PIN, sin login.
 *  - Mi perfil: cada empleado ve y gestiona solo su propia información.
 */
#[Authorize]
class EquipoController extends Controller
{
    #[Route('/kiosco/identificar', 'POST')]
    #[PublicRoute]
    public function identificar()
    {
        $this->ok((new AsistenciaService())->identificar((string)$this->input('pin', '')));
    }

    #[Route('/kiosco/marcar', 'POST')]
    #[PublicRoute]
    public function marcar()
    {
        $this->ok((new AsistenciaService())->marcarConPin(
            (string)$this->input('pin', ''), (string)$this->input('tipo', ''), $this->input('foto')
        ), 'Marcación registrada', 201);
    }

    #[Route('/perfil', 'GET')]
    public function perfil()
    {
        $this->ok((new PerfilService())->get(Auth::id()));
    }

    #[Route('/perfil/marcar', 'POST')]
    public function marcarPerfil()
    {
        $emp = (new EmpleadoRepository())->find(Auth::id());
        $this->ok((new AsistenciaService())->marcar($emp, (string)$this->input('tipo', ''), $this->input('foto'), 'perfil'), 'Marcación registrada', 201);
    }

    #[Route('/perfil/solicitudes', 'POST')]
    public function solicitar()
    {
        $this->ok((new SolicitudService())->crear(Auth::id(), SolicitudDTO::fromRequest($this->input())), 'Solicitud enviada', 201);
    }

    #[Route('/perfil/solicitudes/{id}/cancelar', 'PUT')]
    public function cancelarSolicitud(string $id)
    {
        $this->ok((new SolicitudService())->cancelar((int)$id, Auth::id()), 'Solicitud cancelada');
    }

    #[Route('/perfil/password', 'PUT')]
    public function password()
    {
        (new PerfilService())->cambiarPassword(Auth::id(), (string)$this->input('actual', ''), (string)$this->input('nueva', ''));
        $this->ok(null, 'Contraseña actualizada');
    }
}
