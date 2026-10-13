<?php
namespace App\Entities;

/**
 * Mesa con su estado derivado. El estado NO se guarda en la base de datos:
 * se calcula a partir de la cuenta abierta y los llamados pendientes.
 *
 * Prioridad: llamando > pidió la cuenta > por cobrar > ocupada > libre
 */
class MesaEntity
{
    public const LIBRE = 'libre';
    public const OCUPADA = 'ocupada';
    public const POR_COBRAR = 'por_cobrar';
    public const LLAMANDO = 'llamando';
    public const CUENTA = 'cuenta';

    public static function estado(array $r): string
    {
        if (!empty($r['llamado_mesero_en'])) return self::LLAMANDO;
        if (!empty($r['llamado_cuenta_en'])) return self::CUENTA;
        if (!empty($r['cuenta_id'])) {
            // por cobrar: todo lo pedido ya se entregó y queda saldo
            $pend = (int)($r['comandas_pendientes'] ?? 0);
            $items = (int)($r['items'] ?? 0);
            if ($items > 0 && $pend === 0) return self::POR_COBRAR;
            return self::OCUPADA;
        }
        return self::LIBRE;
    }

    public static function toArray(array $r, bool $withToken = false): array
    {
        $saldo = round((float)($r['subtotal'] ?? 0) - (float)($r['descuento'] ?? 0) - (float)($r['pagado'] ?? 0), 2);
        $a = [
            'id' => (int)$r['id'],
            'numero' => (int)$r['numero'],
            'nombre' => $r['nombre'] ?: ('Mesa ' . $r['numero']),
            'capacidad' => (int)$r['capacidad'],
            'zona' => $r['zona'],
            'forma' => $r['forma'],
            'pos_x' => (float)$r['pos_x'],
            'pos_y' => (float)$r['pos_y'],
            'activa' => (bool)$r['activa'],
            'estado' => self::estado($r),
            'cuenta' => !empty($r['cuenta_id']) ? [
                'id' => (int)$r['cuenta_id'],
                'subtotal' => round((float)$r['subtotal'], 2),
                'saldo' => $saldo,
                'personas' => (int)($r['personas'] ?? 1),
                'abierta_en' => $r['abierta_en'] ?? null,
                'mesero' => $r['mesero'] ?? null,
                'comandas_pendientes' => (int)($r['comandas_pendientes'] ?? 0),
            ] : null,
            'llamado' => !empty($r['llamado_mesero_en'])
                ? ['tipo' => 'mesero', 'id' => (int)$r['llamado_mesero_id'], 'desde' => $r['llamado_mesero_en']]
                : (!empty($r['llamado_cuenta_en'])
                    ? ['tipo' => 'cuenta', 'id' => (int)$r['llamado_cuenta_id'], 'desde' => $r['llamado_cuenta_en'], 'metodo_pago' => $r['llamado_cuenta_metodo'] ?? null]
                    : null),
        ];
        if ($withToken) $a['qr_token'] = $r['qr_token'];
        return $a;
    }
}
