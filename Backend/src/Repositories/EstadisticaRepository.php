<?php
namespace App\Repositories;

/** Consultas de ventas para el dashboard. Siempre sobre cuentas pagadas por fecha de cierre. */
class EstadisticaRepository extends Repository
{
    private const W = "c.estado = 'pagada' AND c.cerrada_en >= ? AND c.cerrada_en < DATE_ADD(?, INTERVAL 1 DAY)";

    public function kpis(string $desde, string $hasta): array
    {
        $r = $this->one(
            "SELECT COUNT(*) AS cuentas,
                    COALESCE(SUM(c.subtotal - c.descuento),0) AS ventas,
                    COALESCE(SUM(c.propina),0) AS propinas,
                    COALESCE(SUM(c.descuento),0) AS descuentos,
                    COALESCE(SUM(c.personas),0) AS comensales
               FROM cuentas c WHERE " . self::W,
            [$desde, $hasta]
        );
        $costo = (float)$this->value(
            "SELECT COALESCE(SUM(ci.costo * ci.cantidad),0)
               FROM comanda_items ci JOIN cuentas c ON c.id = ci.cuenta_id
              WHERE ci.estado = 'activo' AND " . self::W,
            [$desde, $hasta]
        );
        $platillos = (int)$this->value(
            "SELECT COALESCE(SUM(ci.cantidad),0) FROM comanda_items ci JOIN cuentas c ON c.id = ci.cuenta_id
              WHERE ci.estado = 'activo' AND " . self::W,
            [$desde, $hasta]
        );
        $ventas = round((float)$r['ventas'], 2);
        $cuentas = (int)$r['cuentas'];
        return [
            'ventas' => $ventas,
            'cuentas' => $cuentas,
            'ticket_promedio' => $cuentas ? round($ventas / $cuentas, 2) : 0,
            'propinas' => round((float)$r['propinas'], 2),
            'descuentos' => round((float)$r['descuentos'], 2),
            'costo' => round($costo, 2),
            'utilidad' => round($ventas - $costo, 2),
            'platillos' => $platillos,
            'comensales' => (int)$r['comensales'],
        ];
    }

    /** Ingresos por día (o por hora si el periodo es un solo día). */
    public function serie(string $desde, string $hasta, bool $porHora): array
    {
        $g = $porHora ? "HOUR(c.cerrada_en)" : "DATE(c.cerrada_en)";
        return $this->all(
            "SELECT $g AS k, COUNT(*) AS cuentas, COALESCE(SUM(c.subtotal - c.descuento),0) AS ventas
               FROM cuentas c WHERE " . self::W . " GROUP BY k ORDER BY k",
            [$desde, $hasta]
        );
    }

    public function topPlatillos(string $desde, string $hasta, int $limit = 8): array
    {
        return $this->all(
            "SELECT ci.nombre, MAX(ci.producto_id) AS producto_id, SUM(ci.cantidad) AS unidades,
                    SUM(ci.cantidad * ci.precio) AS ventas, SUM(ci.cantidad * (ci.precio - ci.costo)) AS utilidad
               FROM comanda_items ci JOIN cuentas c ON c.id = ci.cuenta_id
              WHERE ci.estado = 'activo' AND " . self::W . "
              GROUP BY ci.nombre ORDER BY unidades DESC, ventas DESC LIMIT $limit",
            [$desde, $hasta]
        );
    }

    public function porCategoria(string $desde, string $hasta): array
    {
        return $this->all(
            "SELECT COALESCE(ci.categoria,'Sin categoría') AS categoria, SUM(ci.cantidad * ci.precio) AS ventas, SUM(ci.cantidad) AS unidades
               FROM comanda_items ci JOIN cuentas c ON c.id = ci.cuenta_id
              WHERE ci.estado = 'activo' AND " . self::W . "
              GROUP BY categoria ORDER BY ventas DESC",
            [$desde, $hasta]
        );
    }

    public function porMesero(string $desde, string $hasta): array
    {
        return $this->all(
            "SELECT c.mesero_id, COALESCE(CONCAT(e.nombre,' ',e.apellido),'Pedidos desde el QR') AS mesero, e.foto,
                    COUNT(*) AS cuentas, SUM(c.subtotal - c.descuento) AS ventas, SUM(c.propina) AS propinas
               FROM cuentas c LEFT JOIN empleados e ON e.id = c.mesero_id
              WHERE " . self::W . "
              GROUP BY c.mesero_id ORDER BY ventas DESC",
            [$desde, $hasta]
        );
    }

    public function porMetodo(string $desde, string $hasta): array
    {
        return $this->all(
            "SELECT metodo, COUNT(*) AS n, SUM(monto) AS monto FROM pagos
              WHERE anulado = 0 AND creado_en >= ? AND creado_en < DATE_ADD(?, INTERVAL 1 DAY)
              GROUP BY metodo",
            [$desde, $hasta]
        );
    }

    /** Ventas de un mesero en un periodo (ficha del empleado). */
    public function ventasMesero(int $empleadoId, string $desde, string $hasta): array
    {
        return $this->one(
            "SELECT COUNT(*) AS cuentas, COALESCE(SUM(c.subtotal - c.descuento),0) AS ventas, COALESCE(SUM(c.propina),0) AS propinas_cobradas
               FROM cuentas c WHERE c.mesero_id = ? AND " . self::W,
            [$empleadoId, $desde, $hasta]
        );
    }
}
