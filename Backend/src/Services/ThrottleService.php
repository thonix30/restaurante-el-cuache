<?php
namespace App\Services;

use App\Core\HttpException;
use App\Repositories\LoginIntentoRepository;
use App\Utils\Auth;
use App\Utils\Config;

/**
 * Bloqueo progresivo por IP tras varios intentos fallidos (login y PIN del kiosco).
 * Con 5 fallos se bloquea 60 s; cada fallo extra duplica la espera (máx. 15 min).
 * Devuelve los segundos restantes para que la pantalla muestre la cuenta regresiva.
 */
class ThrottleService
{
    private LoginIntentoRepository $repo;
    private const WINDOW = 900;

    public function __construct(private string $tipo = 'login')
    {
        $this->repo = new LoginIntentoRepository();
    }

    public function retryAfter(): int
    {
        $max = (int)Config::get('login_max_intentos', 5);
        $base = (int)Config::get('login_bloqueo_seg', 60);
        $r = $this->repo->recentFailures(Auth::ip(), $this->tipo, self::WINDOW);
        if ($r['fallos'] < $max || !$r['ultimo']) return 0;
        $espera = min(self::WINDOW, $base * (2 ** ($r['fallos'] - $max)));
        return max(0, strtotime($r['ultimo']) + $espera - time());
    }

    public function intentosRestantes(): int
    {
        $max = (int)Config::get('login_max_intentos', 5);
        $r = $this->repo->recentFailures(Auth::ip(), $this->tipo, self::WINDOW);
        return max(0, $max - $r['fallos']);
    }

    public function assertNotLocked(): void
    {
        $s = $this->retryAfter();
        if ($s > 0) {
            throw new HttpException("Demasiados intentos. Espera $s segundos.", 429, ['retry_after' => $s]);
        }
    }

    public function hit(bool $ok, ?string $usuario = null): void
    {
        $this->repo->record(Auth::ip(), $this->tipo, $usuario, $ok);
    }
}
