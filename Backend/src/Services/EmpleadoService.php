<?php
namespace App\Services;

use App\Core\HttpException;
use App\DTOs\EmpleadoDTO;
use App\Entities\EmpleadoEntity;
use App\Repositories\EmpleadoRepository;
use App\Repositories\EstadisticaRepository;
use App\Repositories\SolicitudRepository;
use App\Utils\Audit;
use App\Utils\Auth;
use App\Utils\Storage;
use App\Utils\Time;

class EmpleadoService
{
    private EmpleadoRepository $repo;

    public function __construct()
    {
        $this->repo = new EmpleadoRepository();
    }

    /** Lista de personal + KPIs (activos, en turno ahora, tardanzas de hoy, faltas de la semana). */
    public function list(array $f): array
    {
        $rows = $this->repo->list($f);
        $todos = $f ? $this->repo->list([]) : $rows;

        $hoy = Time::today();
        $lunes = Time::weekStart($hoy);
        $asis = (new AsistenciaService())->calcular($lunes, $hoy);
        $tardanzasHoy = 0; $faltasSemana = 0;
        foreach ($asis as $dias) {
            foreach ($dias as $fecha => $r) {
                if ($fecha === $hoy && in_array('tarde', $r['estados'], true)) $tardanzasHoy++;
                if (in_array('falta', $r['estados'], true) && !in_array('justificada', $r['estados'], true)) $faltasSemana++;
            }
        }
        return [
            'empleados' => array_map(fn($r) => EmpleadoEntity::fromRow($r)->toSummary(), $rows),
            'kpis' => [
                'activos' => count(array_filter($todos, fn($e) => $e['estado'] === 'activo')),
                'en_turno' => count(array_filter($todos, fn($e) => $e['x_en_turno'])),
                'tardanzas_hoy' => $tardanzasHoy,
                'faltas_semana' => $faltasSemana,
            ],
        ];
    }

    public function get(int $id): array
    {
        $e = $this->repo->find($id);
        if (!$e) throw HttpException::notFound('El empleado no existe.');
        $data = EmpleadoEntity::fromRow($e)->toArray();
        $data['documentos'] = $this->documentos($id);
        $data['vacaciones'] = $this->vacaciones($e);
        $data['antiguedad'] = $this->antiguedad($e['fecha_ingreso']);
        $desde = Time::addDays(Time::today(), -29);
        $ventas = (new EstadisticaRepository())->ventasMesero($id, $desde, Time::today());
        $propinas = (new ReporteService())->propinasPorEmpleado($desde, Time::today());
        $data['ventas_30d'] = [
            'cuentas' => (int)$ventas['cuentas'],
            'ventas' => round((float)$ventas['ventas'], 2),
            'propinas_repartidas' => round($propinas[$id] ?? 0, 2),
        ];
        return $data;
    }

    public function create(EmpleadoDTO $dto): array
    {
        $fields = $dto->fields + ['fecha_ingreso' => Time::today()];
        $id = $this->repo->create($fields);
        Audit::log('empleado_creado', 'personal', 'empleado', $id, "Nuevo empleado: {$fields['nombre']} " . ($fields['apellido'] ?? '') . " ({$fields['puesto']})");
        return $this->get($id);
    }

    public function update(int $id, EmpleadoDTO $dto): array
    {
        $antes = $this->repo->find($id);
        if (!$antes) throw HttpException::notFound('El empleado no existe.');
        $f = $dto->fields;
        if (isset($f['estado']) && in_array($f['estado'], ['baja', 'suspendido'], true) && $antes['rol'] === 'Admin' && $this->repo->adminsActivos($id) === 0) {
            throw HttpException::conflict('Es el único administrador con acceso. Asigna otro antes de cambiar su estado.');
        }
        if (isset($f['estado']) && $f['estado'] !== 'baja') { $f['fecha_baja'] = null; $f['motivo_baja'] = null; }
        if (array_key_exists('foto', $f) && $antes['foto'] && $antes['foto'] !== $f['foto']) Storage::delete($antes['foto']);
        $this->repo->updateFields($id, $f);

        $cambios = [];
        foreach (['salario', 'tarifa_hora', 'puesto', 'estado', 'tipo_pago'] as $k) {
            if (isset($f[$k]) && (string)$f[$k] !== (string)$antes[$k]) $cambios[$k] = ['antes' => $antes[$k], 'despues' => $f[$k]];
        }
        Audit::log('empleado_editado', 'personal', 'empleado', $id,
            'Ficha de ' . trim($antes['nombre'] . ' ' . $antes['apellido']) . ' actualizada' . ($cambios ? ' (' . implode(', ', array_keys($cambios)) . ')' : ''),
            $cambios ?: null);
        return $this->get($id);
    }

    public function baja(int $id, string $motivo, ?string $fecha): array
    {
        $e = $this->repo->find($id);
        if (!$e) throw HttpException::notFound('El empleado no existe.');
        if (trim($motivo) === '') throw HttpException::invalid('Indica el motivo de la baja.', ['errors' => ['motivo' => 'Obligatorio.']]);
        if ($e['rol'] === 'Admin' && $e['acceso_activo'] && $this->repo->adminsActivos($id) === 0) {
            throw HttpException::conflict('Es el único administrador con acceso. Asigna otro antes de darlo de baja.');
        }
        $this->repo->updateFields($id, [
            'estado' => 'baja', 'acceso_activo' => 0, 'fecha_baja' => $fecha ?: Time::today(), 'motivo_baja' => mb_substr(trim($motivo), 0, 255),
        ]);
        // Sus turnos futuros dejan de contar
        (new \App\Repositories\TurnoRepository())->deleteRango(Time::addDays(Time::today(), 1), Time::addDays(Time::today(), 400), $id);
        Audit::log('empleado_baja', 'personal', 'empleado', $id, trim($e['nombre'] . ' ' . $e['apellido']) . " dado de baja: $motivo");
        return $this->get($id);
    }

    /** Acceso al sistema: interruptor, usuario, rol y contraseña. */
    public function acceso(int $id, array $d): array
    {
        $e = $this->repo->find($id);
        if (!$e) throw HttpException::notFound('El empleado no existe.');
        $activo = filter_var($d['acceso_activo'] ?? $e['acceso_activo'], FILTER_VALIDATE_BOOLEAN);
        $usuario = strtolower(trim((string)($d['usuario'] ?? $e['usuario'] ?? '')));
        $rol = $d['rol'] ?? $e['rol'];
        $password = (string)($d['password'] ?? '');
        $errors = [];

        if ($activo) {
            if (!preg_match('/^[a-z0-9._-]{3,40}$/', $usuario)) $errors['usuario'] = 'Usa de 3 a 40 letras, números, punto o guion.';
            elseif ($this->repo->usuarioEnUso($usuario, $id)) $errors['usuario'] = 'Ese usuario ya existe.';
            if (!in_array($rol, ['Admin', 'Mesero', 'Cocinero'], true)) $errors['rol'] = 'Elige un rol.';
            if (!$e['password'] && $password === '') $errors['password'] = 'Define una contraseña inicial.';
            if ($e['estado'] === 'baja') $errors['acceso_activo'] = 'El empleado está dado de baja.';
        }
        if ($password !== '' && mb_strlen($password) < 6) $errors['password'] = 'Mínimo 6 caracteres.';
        if ($errors) throw HttpException::invalid(reset($errors), ['errors' => $errors]);

        $quitaAdmin = $e['rol'] === 'Admin' && $e['acceso_activo'] && (!$activo || $rol !== 'Admin');
        if ($quitaAdmin && $this->repo->adminsActivos($id) === 0) {
            throw HttpException::conflict('Debe quedar al menos un administrador con acceso.');
        }

        $upd = ['acceso_activo' => $activo ? 1 : 0, 'usuario' => $usuario ?: null, 'rol' => $activo || $rol ? $rol : null];
        if ($password !== '') $upd['password'] = password_hash($password, PASSWORD_DEFAULT);
        $this->repo->updateFields($id, $upd);

        Audit::log('acceso_modificado', 'acceso', 'empleado', $id,
            trim($e['nombre'] . ' ' . $e['apellido']) . ': acceso ' . ($activo ? "activo como $rol" : 'desactivado') . ($password !== '' ? ' · contraseña restablecida' : ''),
            ['acceso_activo' => $activo, 'rol' => $rol, 'usuario' => $usuario, 'password_cambiado' => $password !== '']);
        return $this->get($id);
    }

    public function pin(int $id, string $pin): array
    {
        $e = $this->repo->find($id);
        if (!$e) throw HttpException::notFound('El empleado no existe.');
        if (!preg_match('/^\d{4}$/', $pin)) throw HttpException::invalid('El PIN debe tener 4 dígitos.', ['errors' => ['pin' => '4 dígitos.']]);
        if (preg_match('/^(\d)\1{3}$/', $pin)) {
            throw HttpException::invalid('Ese PIN es demasiado fácil de adivinar.', ['errors' => ['pin' => 'Elige otro.']]);
        }
        $hash = Auth::pinHash($pin);
        if ($this->repo->pinEnUso($hash, $id)) throw HttpException::invalid('Ese PIN ya lo usa otra persona.', ['errors' => ['pin' => 'Elige otro.']]);
        $this->repo->updateFields($id, ['pin_hash' => $hash]);
        Audit::log('pin_asignado', 'acceso', 'empleado', $id, 'PIN de asistencia actualizado para ' . trim($e['nombre'] . ' ' . $e['apellido']));
        return ['tiene_pin' => true];
    }

    // ── Documentos ──────────────────────────────────────────────────────
    public function documentos(int $id): array
    {
        return array_map(fn($d) => [
            'id' => (int)$d['id'], 'tipo' => $d['tipo'], 'nombre' => $d['nombre'], 'mime' => $d['mime'],
            'vence_en' => $d['vence_en'], 'creado_en' => $d['creado_en'],
            'dias_para_vencer' => $d['dias_para_vencer'] !== null ? (int)$d['dias_para_vencer'] : null,
        ], $this->repo->documentos($id));
    }

    public function subirDocumento(int $id, array $file, array $d): array
    {
        if (!$this->repo->find($id)) throw HttpException::notFound('El empleado no existe.');
        $tipo = in_array($d['tipo'] ?? '', ['Contrato', 'Identificación', 'Certificado de salud', 'Otro'], true) ? $d['tipo'] : 'Otro';
        $vence = !empty($d['vence_en']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['vence_en']) ? $d['vence_en'] : null;
        $saved = Storage::saveUploadedDocument($file, 'documentos');
        $nombre = trim((string)($d['nombre'] ?? '')) ?: $tipo;
        $this->repo->addDocumento([
            'empleado_id' => $id, 'tipo' => $tipo, 'nombre' => mb_substr($nombre, 0, 150),
            'archivo' => $saved['path'], 'mime' => $saved['mime'], 'vence_en' => $vence, 'subido_por' => Auth::id(),
        ]);
        Audit::log('documento_subido', 'personal', 'empleado', $id, "Documento «$nombre» ($tipo) agregado");
        return $this->documentos($id);
    }

    public function archivoDocumento(int $empleadoId, int $docId): array
    {
        $d = $this->repo->documento($docId);
        if (!$d || (int)$d['empleado_id'] !== $empleadoId) throw HttpException::notFound('El documento no existe.');
        return ['path' => Storage::absolute($d['archivo']), 'nombre' => $d['nombre'] . '.' . pathinfo($d['archivo'], PATHINFO_EXTENSION), 'mime' => $d['mime']];
    }

    public function borrarDocumento(int $empleadoId, int $docId): array
    {
        $d = $this->repo->documento($docId);
        if (!$d || (int)$d['empleado_id'] !== $empleadoId) throw HttpException::notFound('El documento no existe.');
        $this->repo->deleteDocumento($docId);
        Storage::delete($d['archivo']);
        Audit::log('documento_eliminado', 'personal', 'empleado', $empleadoId, "Documento «{$d['nombre']}» eliminado");
        return $this->documentos($empleadoId);
    }

    // ── Helpers ─────────────────────────────────────────────────────────
    public function vacaciones(array $e): array
    {
        $anio = (int)date('Y');
        $repo = new SolicitudRepository();
        $usados = $repo->diasVacacionesAprobados((int)$e['id'], $anio);
        $reservados = $repo->diasVacacionesUsados((int)$e['id'], $anio);
        $anuales = (int)$e['dias_vacaciones'];
        return [
            'anuales' => $anuales,
            'usados' => $usados,
            'pendientes_aprobacion' => $reservados - $usados,
            'disponibles' => max(0, $anuales - $reservados),
        ];
    }

    private function antiguedad(?string $ingreso): ?string
    {
        if (!$ingreso) return null;
        $d = (new \DateTime($ingreso))->diff(new \DateTime());
        if ($d->y > 0) return $d->y . ' año' . ($d->y > 1 ? 's' : '') . ($d->m ? " y {$d->m} mes" . ($d->m > 1 ? 'es' : '') : '');
        if ($d->m > 0) return $d->m . ' mes' . ($d->m > 1 ? 'es' : '');
        return $d->days . ' día' . ($d->days !== 1 ? 's' : '');
    }
}
