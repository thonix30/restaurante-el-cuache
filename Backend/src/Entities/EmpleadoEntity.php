<?php
namespace App\Entities;

use App\Utils\Url;

class EmpleadoEntity
{
    private const PUBLIC_FIELDS = [
        'id', 'nombre', 'apellido', 'foto', 'puesto', 'estado', 'telefono', 'email',
        'rol', 'acceso_activo', 'usuario', 'fecha_ingreso', 'fecha_nacimiento',
    ];

    private const INT_FIELDS = ['id', 'dias_vacaciones'];
    private const BOOL_FIELDS = ['acceso_activo'];
    private const FLOAT_FIELDS = ['salario', 'tarifa_hora'];
    private const HIDDEN = ['password', 'pin_hash'];

    public function __construct(private array $row)
    {
    }

    public static function fromRow(array $r): self
    {
        return new self($r);
    }

    private function cast(array $a): array
    {
        foreach ($a as $k => $v) {
            if (in_array($k, self::INT_FIELDS, true) && $v !== null) $a[$k] = (int)$v;
            if (in_array($k, self::BOOL_FIELDS, true)) $a[$k] = (bool)$v;
            if (in_array($k, self::FLOAT_FIELDS, true) && $v !== null) $a[$k] = (float)$v;
        }
        if (array_key_exists('foto', $a)) $a['foto'] = Url::asset($a['foto']);
        $a['nombre_completo'] = trim(($this->row['nombre'] ?? '') . ' ' . ($this->row['apellido'] ?? ''));
        return $a;
    }

    /** Datos para listas y tarjetas. */
    public function toSummary(): array
    {
        $a = array_intersect_key($this->row, array_flip(self::PUBLIC_FIELDS));
        foreach ($this->row as $k => $v) {
            // columnas calculadas que vienen del repositorio (en_turno, etc.)
            if (str_starts_with($k, 'x_')) $a[substr($k, 2)] = is_numeric($v) ? $v + 0 : $v;
        }
        return $this->cast($a);
    }

    /** Ficha completa (solo Admin o el propio empleado). */
    public function toArray(): array
    {
        $a = array_diff_key($this->row, array_flip(self::HIDDEN));
        $a['tiene_pin'] = !empty($this->row['pin_hash']);
        $a['tiene_password'] = !empty($this->row['password']);
        foreach (array_keys($a) as $k) if (str_starts_with($k, 'x_')) unset($a[$k]);
        return $this->cast($a);
    }

    /** Lo que un empleado ve de sí mismo: sin datos de nómina de otros ni notas internas. */
    public function toSelf(): array
    {
        $a = $this->toArray();
        unset($a['motivo_baja']);
        return $a;
    }
}
