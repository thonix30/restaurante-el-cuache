<?php
namespace App\Controllers;

use App\Attributes\Authorize;
use App\Attributes\Route;
use App\Core\Controller;
use App\Core\HttpException;
use App\DTOs\EmpleadoDTO;
use App\DTOs\SolicitudDTO;
use App\DTOs\TurnoDTO;
use App\Services\AlertaService;
use App\Services\AsistenciaService;
use App\Services\EmpleadoService;
use App\Services\PerfilService;
use App\Services\ReporteService;
use App\Services\SolicitudService;
use App\Services\TurnoService;
use App\Utils\Auth;
use App\Utils\Response;
use App\Utils\Time;

/**
 * Personal: fichas, acceso, documentos, turnos, asistencia, solicitudes y
 * reportes (solo Admin). Kiosco (PIN, sin login) y "Mi perfil" (cada quien lo suyo).
 */
#[Authorize(['Admin'])]
class PersonalController extends Controller
{
    // ── Empleados ───────────────────────────────────────────────────────
    #[Route('/empleados', 'GET')]
    public function lista()
    {
        $this->ok((new EmpleadoService())->list(array_filter([
            'q' => $this->query('q'), 'puesto' => $this->query('puesto'), 'estado' => $this->query('estado'),
        ])));
    }

    #[Route('/empleados', 'POST')]
    public function crear()
    {
        $this->ok((new EmpleadoService())->create(EmpleadoDTO::fromRequest($this->input())), 'Empleado registrado', 201);
    }

    #[Route('/empleados/{id}', 'GET')]
    public function ver(string $id)
    {
        $this->ok((new EmpleadoService())->get((int)$id));
    }

    #[Route('/empleados/{id}', 'PUT')]
    public function editar(string $id)
    {
        $this->ok((new EmpleadoService())->update((int)$id, EmpleadoDTO::fromRequest($this->input(), true)), 'Ficha actualizada');
    }

    #[Route('/empleados/{id}/baja', 'POST')]
    public function baja(string $id)
    {
        $this->ok((new EmpleadoService())->baja((int)$id, (string)$this->input('motivo', ''), $this->input('fecha')), 'Empleado dado de baja');
    }

    #[Route('/empleados/{id}/acceso', 'PUT')]
    public function acceso(string $id)
    {
        $this->ok((new EmpleadoService())->acceso((int)$id, $this->input()), 'Acceso actualizado');
    }

    #[Route('/empleados/{id}/pin', 'PUT')]
    public function pin(string $id)
    {
        $this->ok((new EmpleadoService())->pin((int)$id, (string)$this->input('pin', '')), 'PIN actualizado');
    }

    #[Route('/empleados/{id}/asistencia', 'GET')]
    public function asistenciaEmpleado(string $id)
    {
        $this->ok((new AsistenciaService())->mes((int)$id, (string)$this->query('mes', date('Y-m'))));
    }

    #[Route('/empleados/{id}/documentos', 'POST')]
    public function subirDocumento(string $id)
    {
        if (empty($_FILES['archivo'])) throw HttpException::invalid('Adjunta el archivo.');
        $this->ok((new EmpleadoService())->subirDocumento((int)$id, $_FILES['archivo'], $_POST), 'Documento agregado', 201);
    }

    #[Route('/empleados/{id}/documentos/{doc}', 'GET')]
    public function verDocumento(string $id, string $doc)
    {
        $f = (new EmpleadoService())->archivoDocumento((int)$id, (int)$doc);
        Response::file($f['path'], $f['nombre'], $f['mime']);
    }

    #[Route('/empleados/{id}/documentos/{doc}', 'DELETE')]
    public function borrarDocumento(string $id, string $doc)
    {
        $this->ok((new EmpleadoService())->borrarDocumento((int)$id, (int)$doc), 'Documento eliminado');
    }

    #[Route('/alertas', 'GET')]
    public function alertas()
    {
        $this->ok((new AlertaService())->todas());
    }

    // ── Turnos ──────────────────────────────────────────────────────────
    #[Route('/turnos', 'GET')]
    public function turnos()
    {
        $this->ok((new TurnoService())->semana((string)$this->query('semana', Time::today())));
    }

    #[Route('/turnos', 'POST')]
    public function crearTurno()
    {
        $this->ok((new TurnoService())->crear(TurnoDTO::fromRequest($this->input())), 'Turno creado', 201);
    }

    #[Route('/turnos/copiar', 'POST')]
    public function copiarSemana()
    {
        $this->ok((new TurnoService())->copiarSemanaAnterior((string)$this->input('semana', Time::today()), (bool)$this->input('reemplazar', true)), 'Semana copiada');
    }

    #[Route('/turnos/publicar', 'POST')]
    public function publicar()
    {
        $this->ok((new TurnoService())->publicar((string)$this->input('semana', Time::today())), 'Horario publicado');
    }

    #[Route('/turnos/limpiar', 'POST')]
    public function limpiar()
    {
        $this->ok((new TurnoService())->limpiarSemana((string)$this->input('semana', Time::today())), 'Semana limpia');
    }

    #[Route('/turnos/plantillas', 'GET')]
    public function plantillas()
    {
        $this->ok((new TurnoService())->plantillas());
    }

    #[Route('/turnos/plantillas', 'POST')]
    public function guardarPlantilla()
    {
        $this->ok((new TurnoService())->guardarPlantilla((string)$this->input('nombre', ''), (string)$this->input('semana', Time::today()), (bool)$this->input('rotativa', false)), 'Plantilla guardada', 201);
    }

    #[Route('/turnos/plantillas/{id}/aplicar', 'POST')]
    public function aplicarPlantilla(string $id)
    {
        $this->ok((new TurnoService())->aplicarPlantilla((int)$id, (string)$this->input('semana', Time::today()), (bool)$this->input('reemplazar', true)), 'Plantilla aplicada');
    }

    #[Route('/turnos/plantillas/{id}', 'DELETE')]
    public function borrarPlantilla(string $id)
    {
        (new TurnoService())->borrarPlantilla((int)$id);
        $this->ok(null, 'Plantilla eliminada');
    }

    #[Route('/turnos/{id}', 'PUT')]
    public function editarTurno(string $id)
    {
        $this->ok((new TurnoService())->actualizar((int)$id, TurnoDTO::fromRequest($this->input())), 'Turno actualizado');
    }

    #[Route('/turnos/{id}', 'DELETE')]
    public function borrarTurno(string $id)
    {
        (new TurnoService())->eliminar((int)$id);
        $this->ok(null, 'Turno eliminado');
    }

    // ── Asistencia ──────────────────────────────────────────────────────
    #[Route('/asistencia', 'GET')]
    public function asistencia()
    {
        $f = (string)$this->query('fecha', Time::today());
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) $f = Time::today();
        $this->ok((new AsistenciaService())->dia($f));
    }

    #[Route('/asistencia/corregir', 'POST')]
    public function corregir()
    {
        $i = $this->input();
        $this->ok((new AsistenciaService())->corregir(
            (int)($i['empleado_id'] ?? 0), (string)($i['fecha'] ?? ''), $i['entrada'] ?? null, $i['salida'] ?? null,
            $i['descanso_inicio'] ?? null, $i['descanso_fin'] ?? null, (string)($i['motivo'] ?? '')
        ), 'Asistencia corregida');
    }

    #[Route('/asistencia/justificar', 'POST')]
    public function justificar()
    {
        $i = $_POST ?: $this->input();
        $this->ok((new AsistenciaService())->justificar(
            (int)($i['empleado_id'] ?? 0), (string)($i['fecha'] ?? ''), (string)($i['tipo'] ?? 'falta'),
            (string)($i['motivo'] ?? ''), !empty($_FILES['archivo']) ? $_FILES['archivo'] : null
        ), 'Justificación registrada');
    }

    #[Route('/asistencia/justificaciones/{id}/archivo', 'GET')]
    public function archivoJustificacion(string $id)
    {
        $f = (new AsistenciaService())->archivoJustificacion((int)$id);
        Response::file($f['path'], $f['nombre']);
    }

    #[Route('/asistencia/marcaciones/{id}/foto', 'GET')]
    public function fotoMarcacion(string $id)
    {
        $f = (new AsistenciaService())->fotoMarcacion((int)$id);
        Response::file($f['path'], $f['nombre']);
    }

    #[Route('/reportes/horas', 'GET')]
    public function reporteHoras()
    {
        $this->ok((new ReporteService())->horas($this->query('desde'), $this->query('hasta')));
    }

    // ── Solicitudes ─────────────────────────────────────────────────────
    #[Route('/solicitudes', 'GET')]
    public function solicitudes()
    {
        $this->ok((new SolicitudService())->list($this->query('estado') ?: null));
    }

    #[Route('/solicitudes/{id}/resolver', 'PUT')]
    public function resolver(string $id)
    {
        $this->ok((new SolicitudService())->resolver((int)$id, (bool)$this->input('aprobar'), $this->input('respuesta')), 'Solicitud resuelta');
    }
}
