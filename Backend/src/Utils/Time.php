<?php
namespace App\Utils;

/** Utilidades de fecha/hora (zona America/Guatemala). */
class Time
{
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function today(): string
    {
        return date('Y-m-d');
    }

    public static function ts(?string $dt): ?int
    {
        return $dt ? strtotime($dt) : null;
    }

    /** Inicio y fin reales de un turno (si hora_fin <= hora_inicio, termina al día siguiente). */
    public static function shiftRange(string $fecha, string $inicio, string $fin): array
    {
        $a = strtotime("$fecha $inicio");
        $b = strtotime("$fecha $fin");
        if ($b <= $a) $b += 86400;
        return [$a, $b];
    }

    public static function shiftMinutes(string $inicio, string $fin): int
    {
        [$a, $b] = self::shiftRange('2000-01-01', $inicio, $fin);
        return intdiv($b - $a, 60);
    }

    public static function overlap(int $a1, int $b1, int $a2, int $b2): int
    {
        return max(0, min($b1, $b2) - max($a1, $a2));
    }

    /** Lunes de la semana de $fecha. */
    public static function weekStart(string $fecha): string
    {
        $t = strtotime($fecha);
        $dow = (int)date('N', $t); // 1 = lunes
        return date('Y-m-d', strtotime('-' . ($dow - 1) . ' days', $t));
    }

    public static function addDays(string $fecha, int $days): string
    {
        return date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days', strtotime($fecha)));
    }

    /** Lista de fechas Y-m-d entre dos fechas (inclusive). */
    public static function dates(string $desde, string $hasta): array
    {
        $out = [];
        for ($t = strtotime($desde), $end = strtotime($hasta); $t <= $end; $t = strtotime('+1 day', $t)) {
            $out[] = date('Y-m-d', $t);
            if (count($out) > 400) break;
        }
        return $out;
    }

    public static function daysBetween(string $desde, string $hasta): int
    {
        return (int)round((strtotime($hasta) - strtotime($desde)) / 86400) + 1;
    }

    /** Periodo validado (desde/hasta) con valores por defecto y tope de 1 año. */
    public static function period(?string $desde, ?string $hasta, ?string $defaultDesde = null): array
    {
        $ok = fn($d) => $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
        $hasta = $ok($hasta) ? $hasta : self::today();
        $desde = $ok($desde) ? $desde : ($defaultDesde ?? $hasta);
        if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
        if (self::daysBetween($desde, $hasta) > 366) $desde = self::addDays($hasta, -365);
        return [$desde, $hasta];
    }
}
