<?php
namespace App\DTOs;

class SolicitudDTO extends DTO
{
    public string $tipo;
    public ?string $fecha_inicio;
    public ?string $fecha_fin;
    public ?int $turno_id;
    public ?int $companero_id;
    public ?int $turno_companero_id;
    public ?string $motivo;

    public static function fromRequest(array $data): self
    {
        $dto = new self($data);
        $dto->tipo = (string)$dto->oneOf('tipo', ['vacaciones', 'permiso', 'cambio_turno']);
        $dto->fecha_inicio = $dto->date('fecha_inicio');
        $dto->fecha_fin = $dto->date('fecha_fin') ?? $dto->fecha_inicio;
        $dto->turno_id = $dto->int('turno_id');
        $dto->companero_id = $dto->int('companero_id');
        $dto->turno_companero_id = $dto->int('turno_companero_id');
        $dto->motivo = $dto->str('motivo');

        $dto->required('tipo', $dto->tipo, 'Elige el tipo de solicitud.');
        if ($dto->tipo === 'cambio_turno') {
            if (!$dto->turno_id) $dto->fail('turno_id', 'Elige el turno que quieres cambiar.');
            if (!$dto->companero_id) $dto->fail('companero_id', 'Elige al compañero.');
        } elseif ($dto->tipo) {
            $dto->required('fecha_inicio', $dto->fecha_inicio, 'Indica la fecha.');
            if ($dto->fecha_inicio && $dto->fecha_fin && $dto->fecha_fin < $dto->fecha_inicio) {
                $dto->fail('fecha_fin', 'La fecha final es anterior a la inicial.');
            }
            if ($dto->tipo === 'permiso') $dto->required('motivo', $dto->motivo, 'Cuéntanos el motivo del permiso.');
        }
        $dto->check();
        return $dto;
    }
}
