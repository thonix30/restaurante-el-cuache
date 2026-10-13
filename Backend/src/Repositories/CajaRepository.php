<?php
namespace App\Repositories;

class CajaRepository extends Repository
{
    public function abierto(bool $lock = false): ?array
    {
        return $this->one(
            "SELECT t.*, CONCAT(e.nombre,' ',e.apellido) AS abierto_por_nombre
               FROM turnos_caja t LEFT JOIN empleados e ON e.id = t.abierto_por
              WHERE t.estado = 'abierto' ORDER BY t.id DESC LIMIT 1" . ($lock ? " FOR UPDATE" : "")
        );
    }

    public function find(int $id): ?array
    {
        return $this->one(
            "SELECT t.*, CONCAT(a.nombre,' ',a.apellido) AS abierto_por_nombre, CONCAT(c.nombre,' ',c.apellido) AS cerrado_por_nombre
               FROM turnos_caja t
               LEFT JOIN empleados a ON a.id = t.abierto_por
               LEFT JOIN empleados c ON c.id = t.cerrado_por
              WHERE t.id = ?",
            [$id]
        );
    }

    public function abrir(int $userId, float $monto, ?string $notas): int
    {
        return $this->insert('turnos_caja', ['abierto_por' => $userId, 'monto_inicial' => $monto, 'notas_apertura' => $notas]);
    }

    public function cerrar(int $id, array $d): void
    {
        $this->update('turnos_caja', $id, $d + ['estado' => 'cerrado', 'cerrado_en' => date('Y-m-d H:i:s')]);
    }

    public function historial(int $limit = 30): array
    {
        return $this->all(
            "SELECT t.id, t.monto_inicial, t.efectivo_esperado, t.efectivo_contado, t.diferencia, t.notas_cierre,
                    t.abierto_en, t.cerrado_en, CONCAT(a.nombre,' ',a.apellido) AS abierto_por_nombre,
                    CONCAT(c.nombre,' ',c.apellido) AS cerrado_por_nombre, t.resumen
               FROM turnos_caja t
               LEFT JOIN empleados a ON a.id = t.abierto_por
               LEFT JOIN empleados c ON c.id = t.cerrado_por
              WHERE t.estado = 'cerrado' ORDER BY t.cerrado_en DESC LIMIT $limit"
        );
    }

    /** Descuentos y cancelaciones ocurridos durante el turno. */
    public function movimientosTurno(int $turnoId, string $desde, string $hasta): array
    {
        $desc = $this->one(
            "SELECT COUNT(*) AS n, COALESCE(SUM(descuento),0) AS monto
               FROM cuentas WHERE descuento > 0 AND estado = 'pagada' AND cerrada_en BETWEEN ? AND ?",
            [$desde, $hasta]
        );
        $itemsCanc = $this->one(
            "SELECT COUNT(*) AS n, COALESCE(SUM(ci.precio * ci.cantidad),0) AS monto
               FROM comanda_items ci JOIN comandas co ON co.id = ci.comanda_id
               JOIN cuentas c ON c.id = ci.cuenta_id
              WHERE ci.estado = 'cancelado' AND c.estado <> 'cancelada' AND co.creado_en BETWEEN ? AND ?",
            [$desde, $hasta]
        );
        $cuentasCanc = $this->one(
            "SELECT COUNT(*) AS n,
                    COALESCE(SUM((SELECT COALESCE(SUM(precio*cantidad),0) FROM comanda_items x WHERE x.cuenta_id = c.id)),0) AS monto
               FROM cuentas c WHERE c.estado = 'cancelada' AND c.cerrada_en BETWEEN ? AND ?",
            [$desde, $hasta]
        );
        $cuentas = $this->one(
            "SELECT COUNT(*) AS n, COALESCE(SUM(subtotal - descuento),0) AS ventas
               FROM cuentas WHERE estado = 'pagada' AND cerrada_en BETWEEN ? AND ?",
            [$desde, $hasta]
        );
        return [
            'descuentos' => ['n' => (int)$desc['n'], 'monto' => round((float)$desc['monto'], 2)],
            'cancelaciones' => [
                'items' => (int)$itemsCanc['n'],
                'cuentas' => (int)$cuentasCanc['n'],
                'monto' => round((float)$itemsCanc['monto'] + (float)$cuentasCanc['monto'], 2),
            ],
            'cuentas_cobradas' => (int)$cuentas['n'],
            'ventas' => round((float)$cuentas['ventas'], 2),
        ];
    }

    public function cuentasAbiertas(): array
    {
        return $this->all(
            "SELECT c.id, m.numero AS mesa_numero, c.subtotal - c.descuento - c.pagado AS saldo
               FROM cuentas c LEFT JOIN mesas m ON m.id = c.mesa_id WHERE c.estado = 'abierta'"
        );
    }
}
