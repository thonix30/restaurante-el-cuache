<?php
namespace App\Services;

use App\Core\HttpException;
use App\DTOs\LoginDTO;
use App\Repositories\EmpleadoRepository;
use App\Utils\Audit;
use App\Utils\Auth;
use App\Utils\Url;

class AuthService
{
    private EmpleadoRepository $empleados;
    private ThrottleService $throttle;

    public function __construct()
    {
        $this->empleados = new EmpleadoRepository();
        $this->throttle = new ThrottleService('login');
    }

    public function login(LoginDTO $dto): array
    {
        $this->throttle->assertNotLocked();

        $emp = $this->empleados->findByLogin($dto->usuario);
        $valido = $emp && $emp['password'] && password_verify($dto->password, $emp['password']);
        $conAcceso = $valido && (int)$emp['acceso_activo'] === 1 && $emp['rol'] && !in_array($emp['estado'], ['baja', 'suspendido'], true);

        $this->throttle->hit($conAcceso, $dto->usuario);

        if (!$conAcceso) {
            Audit::log('inicio_sesion_fallido', 'acceso', 'empleado', $emp['id'] ?? null,
                "Intento fallido con el usuario «{$dto->usuario}»", null, ['id' => null, 'nombre' => $dto->usuario]);
            $espera = $this->throttle->retryAfter();
            if ($espera > 0) {
                throw new HttpException("Demasiados intentos. Espera $espera segundos.", 429, ['retry_after' => $espera]);
            }
            $msg = $valido ? 'Tu usuario no tiene acceso al sistema. Habla con administración.' : 'Usuario o contraseña incorrectos.';
            throw new HttpException($msg, 401, ['intentos_restantes' => $this->throttle->intentosRestantes()]);
        }

        if (password_needs_rehash($emp['password'], PASSWORD_DEFAULT)) {
            $this->empleados->updateFields((int)$emp['id'], ['password' => password_hash($dto->password, PASSWORD_DEFAULT)]);
        }
        $this->empleados->touchAcceso((int)$emp['id']);

        $user = $this->publicUser($emp);
        Audit::log('inicio_sesion', 'acceso', 'empleado', $emp['id'], "{$user['nombre']} inició sesión", null, $user);

        return ['token' => Auth::issue($emp), 'usuario' => $user];
    }

    public function me(): array
    {
        $u = Auth::requireRoles();
        $emp = $this->empleados->find($u['id']);
        return $this->publicUser($emp);
    }

    public function logout(): void
    {
        $u = Auth::user();
        if ($u) Audit::log('cierre_sesion', 'acceso', 'empleado', $u['id'], "{$u['nombre']} cerró sesión");
    }

    public function publicUser(array $emp): array
    {
        return [
            'id' => (int)$emp['id'],
            'nombre' => trim($emp['nombre'] . ' ' . $emp['apellido']),
            'nombre_corto' => $emp['nombre'],
            'rol' => $emp['rol'],
            'puesto' => $emp['puesto'],
            'foto' => Url::asset($emp['foto']),
            'usuario' => $emp['usuario'],
        ];
    }
}
