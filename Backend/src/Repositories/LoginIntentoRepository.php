<?php
namespace App\Repositories;

class LoginIntentoRepository extends Repository
{
    public function record(string $ip, string $tipo, ?string $usuario, bool $exito): void
    {
        $this->insert('login_intentos', [
            'ip' => $ip, 'tipo' => $tipo, 'usuario' => $usuario ? mb_substr($usuario, 0, 150) : null, 'exito' => $exito ? 1 : 0,
        ]);
        if (random_int(1, 50) === 1) {
            $this->exec("DELETE FROM login_intentos WHERE creado_en < DATE_SUB(NOW(), INTERVAL 2 DAY)");
        }
    }

    /**
     * Fallos consecutivos de esta IP (desde su último acierto) en la ventana dada,
     * y la hora del último fallo.
     */
    public function recentFailures(string $ip, string $tipo, int $windowSeconds): array
    {
        $row = $this->one(
            "SELECT COUNT(*) AS fallos, MAX(creado_en) AS ultimo
               FROM login_intentos
              WHERE ip = ? AND tipo = ? AND exito = 0
                AND creado_en > DATE_SUB(NOW(), INTERVAL ? SECOND)
                AND creado_en > COALESCE((SELECT MAX(creado_en) FROM login_intentos
                                            WHERE ip = ? AND tipo = ? AND exito = 1), '1970-01-01')",
            [$ip, $tipo, $windowSeconds, $ip, $tipo]
        );
        return ['fallos' => (int)$row['fallos'], 'ultimo' => $row['ultimo']];
    }
}
