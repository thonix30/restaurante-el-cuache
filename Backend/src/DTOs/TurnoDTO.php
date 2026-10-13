<?php
namespace App\DTOs;

class TurnoDTO extends DTO
{
    public int $empleado_id;
    public string $fecha;
    public string $hora_inicio;
    public string $hora_fin;
    public string $tipo;
    public ?string $notas;

    public static function fromRequest(array $data): self
    {
        $dto = new self($data);
        $dto->empleado_id = (int)$dto->int('empleado_id', 0);
        $dto->fecha = (string)$dto->date('fecha');
        $dto->hora_inicio = (string)$dto->time('hora_inicio');
        $dto->hora_fin = (string)$dto->time('hora_fin');
        $dto->tipo = $dto->oneOf('tipo', ['manana', 'tarde', 'noche', 'personalizado'], 'personalizado');
        $dto->notas = $dto->str('notas');
        if ($dto->empleado_id <= 0) $dto->fail('empleado_id', 'Elige un empleado.');
        $dto->required('fecha', $dto->fecha, 'Indica la fecha.');
        $dto->required('hora_inicio', $dto->hora_inicio, 'Indica la hora de inicio.');
        $dto->required('hora_fin', $dto->hora_fin, 'Indica la hora de fin.');
        if ($dto->hora_inicio && $dto->hora_inicio === $dto->hora_fin) $dto->fail('hora_fin', 'El turno no puede durar 0 horas.');
        $dto->check();
        return $dto;
    }
}
