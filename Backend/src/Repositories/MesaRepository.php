<?php
namespace App\Repositories;

class MesaRepository extends Repository
{
    /**
     * Mesas con todo lo necesario para derivar su estado: cuenta abierta,
     * comandas pendientes de entregar y llamados pendientes.
     */
    private const SELECT_ESTADO = "
        SELECT m.*,
               c.id AS cuenta_id, c.subtotal, c.descuento, c.pagado, c.personas, c.abierta_en,
               CONCAT(e.nombre,' ',e.apellido) AS mesero,
               (SELECT COUNT(*) FROM comandas co WHERE co.cuenta_id = c.id AND co.estado IN ('pendiente','preparacion','listo')) AS comandas_pendientes,
               (SELECT COUNT(*) FROM comanda_items ci WHERE ci.cuenta_id = c.id AND ci.estado = 'activo') AS items,
               lm.id AS llamado_mesero_id, lm.creado_en AS llamado_mesero_en,
               lc.id AS llamado_cuenta_id, lc.creado_en AS llamado_cuenta_en, lc.metodo_pago AS llamado_cuenta_metodo
          FROM mesas m
          LEFT JOIN cuentas c ON c.mesa_id = m.id AND c.estado = 'abierta'
          LEFT JOIN empleados e ON e.id = c.mesero_id
          LEFT JOIN llamados lm ON lm.id = (SELECT id FROM llamados WHERE mesa_id = m.id AND tipo = 'mesero' AND estado = 'pendiente' ORDER BY id LIMIT 1)
          LEFT JOIN llamados lc ON lc.id = (SELECT id FROM llamados WHERE mesa_id = m.id AND tipo = 'cuenta' AND estado = 'pendiente' ORDER BY id LIMIT 1)";

    public function allWithEstado(bool $soloActivas = true): array
    {
        return $this->all(self::SELECT_ESTADO . ($soloActivas ? " WHERE m.activa = 1" : "") . " ORDER BY m.numero");
    }

    public function findWithEstado(int $id): ?array
    {
        return $this->one(self::SELECT_ESTADO . " WHERE m.id = ?", [$id]);
    }

    public function find(int $id, bool $lock = false): ?array
    {
        return $this->one("SELECT * FROM mesas WHERE id = ?" . ($lock ? " FOR UPDATE" : ""), [$id]);
    }

    public function findByToken(string $token): ?array
    {
        return $this->one("SELECT * FROM mesas WHERE qr_token = ? AND activa = 1", [$token]);
    }

    public function numeroEnUso(int $numero, ?int $exceptId = null): bool
    {
        return (bool)$this->value("SELECT COUNT(*) FROM mesas WHERE numero = ? AND id <> ?", [$numero, $exceptId ?? 0]);
    }

    public function create(array $d): int
    {
        return $this->insert('mesas', $d);
    }

    public function save(int $id, array $d): void
    {
        $this->update('mesas', $id, $d);
    }

    public function tieneHistorial(int $id): bool
    {
        return (bool)$this->value("SELECT COUNT(*) FROM cuentas WHERE mesa_id = ?", [$id]);
    }

    public function delete(int $id): void
    {
        $this->exec("DELETE FROM mesas WHERE id = ?", [$id]);
    }
}
