<?php
namespace App\Repositories;

class LlamadoRepository extends Repository
{
    public function pendiente(int $mesaId, string $tipo): ?array
    {
        return $this->one(
            "SELECT * FROM llamados WHERE mesa_id = ? AND tipo = ? AND estado = 'pendiente' ORDER BY id LIMIT 1",
            [$mesaId, $tipo]
        );
    }

    public function pendientesDeMesa(int $mesaId): array
    {
        return $this->all("SELECT * FROM llamados WHERE mesa_id = ? AND estado = 'pendiente' ORDER BY id", [$mesaId]);
    }

    public function find(int $id): ?array
    {
        return $this->one("SELECT * FROM llamados WHERE id = ?", [$id]);
    }

    public function create(int $mesaId, string $tipo, ?string $metodo): int
    {
        return $this->insert('llamados', ['mesa_id' => $mesaId, 'tipo' => $tipo, 'metodo_pago' => $metodo]);
    }

    public function setMetodo(int $id, ?string $metodo): void
    {
        $this->exec("UPDATE llamados SET metodo_pago = ? WHERE id = ?", [$metodo, $id]);
    }

    public function cancelar(int $id): int
    {
        return $this->exec("UPDATE llamados SET estado = 'cancelado', atendido_en = NOW() WHERE id = ? AND estado = 'pendiente'", [$id]);
    }

    public function atender(int $id, ?int $empleadoId): int
    {
        return $this->exec(
            "UPDATE llamados SET estado = 'atendido', atendido_en = NOW(), atendido_por = ? WHERE id = ? AND estado = 'pendiente'",
            [$empleadoId, $id]
        );
    }

    public function atenderDeMesa(int $mesaId, ?int $empleadoId, ?string $tipo = null): void
    {
        $sql = "UPDATE llamados SET estado = 'atendido', atendido_en = NOW(), atendido_por = ? WHERE mesa_id = ? AND estado = 'pendiente'";
        $p = [$empleadoId, $mesaId];
        if ($tipo) { $sql .= " AND tipo = ?"; $p[] = $tipo; }
        $this->exec($sql, $p);
    }

    /** Bandeja de avisos: pendientes ordenados por tiempo de espera (más antiguo primero). */
    public function pendientes(): array
    {
        return $this->all(
            "SELECT l.id, l.tipo, l.metodo_pago, l.creado_en, m.id AS mesa_id, m.numero AS mesa_numero,
                    TIMESTAMPDIFF(SECOND, l.creado_en, NOW()) AS espera_seg,
                    c.mesero_id
               FROM llamados l
               JOIN mesas m ON m.id = l.mesa_id
               LEFT JOIN cuentas c ON c.mesa_id = m.id AND c.estado = 'abierta'
              WHERE l.estado = 'pendiente'
              ORDER BY l.creado_en"
        );
    }

    /** Llamados olvidados (más de 2 h) se cierran solos para no ensuciar la bandeja. */
    public function expirarViejos(): void
    {
        $this->exec("UPDATE llamados SET estado = 'cancelado' WHERE estado = 'pendiente' AND creado_en < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    }
}
