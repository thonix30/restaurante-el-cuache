<?php
namespace App\DTOs;

/** Ficha del empleado (datos personales, contrato y pago). El acceso al sistema va aparte. */
class EmpleadoDTO extends DTO
{
    public const PUESTOS = ['Mesero', 'Cocinero', 'Bartender', 'Cajero', 'Ayudante', 'Administrador'];
    public const ESTADOS = ['activo', 'vacaciones', 'suspendido', 'baja'];

    public array $fields = [];

    public static function fromRequest(array $data, bool $partial = false): self
    {
        $dto = new self($data);
        $f = [
            'nombre' => $dto->str('nombre', 80),
            'apellido' => (string)$dto->str('apellido', 80, ''),
            'documento_identidad' => $dto->str('documento_identidad', 30),
            'fecha_nacimiento' => $dto->date('fecha_nacimiento'),
            'telefono' => $dto->str('telefono', 30),
            'email' => $dto->str('email', 150),
            'direccion' => $dto->str('direccion', 255),
            'emergencia_nombre' => $dto->str('emergencia_nombre', 120),
            'emergencia_parentesco' => $dto->str('emergencia_parentesco', 40),
            'emergencia_telefono' => $dto->str('emergencia_telefono', 30),
            'puesto' => $dto->oneOf('puesto', self::PUESTOS, 'Mesero'),
            'fecha_ingreso' => $dto->date('fecha_ingreso'),
            'tipo_contrato' => $dto->oneOf('tipo_contrato', ['Indefinido', 'Temporal', 'Por horas', 'Prueba'], 'Indefinido'),
            'tipo_pago' => $dto->oneOf('tipo_pago', ['Mensual', 'Por hora'], 'Mensual'),
            'salario' => max(0, (float)$dto->num('salario', 0)),
            'tarifa_hora' => max(0, (float)$dto->num('tarifa_hora', 0)),
            'banco' => $dto->str('banco', 60),
            'cuenta_bancaria' => $dto->str('cuenta_bancaria', 40),
            'talla_camisa' => $dto->str('talla_camisa', 10),
            'talla_pantalon' => $dto->str('talla_pantalon', 10),
            'talla_calzado' => $dto->str('talla_calzado', 10),
            'notas_medicas' => $dto->str('notas_medicas', 2000),
            'estado' => $dto->oneOf('estado', self::ESTADOS, 'activo'),
            'dias_vacaciones' => max(0, (int)$dto->int('dias_vacaciones', 15)),
        ];
        if (array_key_exists('foto', $data)) {
            $foto = $dto->str('foto', 255);
            if ($foto && preg_match('@/(uploads/[\w.\-]+)$@', $foto, $m)) $foto = $m[1];
            $f['foto'] = $foto;
        }
        if (!$partial || array_key_exists('nombre', $data)) $dto->required('nombre', $f['nombre'], 'Escribe el nombre.');
        if ($f['email'] && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $dto->fail('email', 'Correo inválido.');
        if ($f['fecha_nacimiento'] && $f['fecha_nacimiento'] > date('Y-m-d', strtotime('-14 years'))) {
            $dto->fail('fecha_nacimiento', 'Revisa la fecha de nacimiento.');
        }
        $dto->check();
        // Solo se actualizan los campos que vinieron en la petición
        $dto->fields = array_filter($f, fn($k) => array_key_exists($k, $data), ARRAY_FILTER_USE_KEY);
        return $dto;
    }
}
