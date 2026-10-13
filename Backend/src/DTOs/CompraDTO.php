<?php
namespace App\DTOs;

class CompraDTO extends DTO
{
    public string $proveedor;
    public int $producto_id;
    public int $cantidad;
    public float $costo_unitario;
    public ?string $notas;

    public static function fromRequest(array $data): self
    {
        $dto = new self($data);
        $dto->proveedor = (string)$dto->str('proveedor', 120, '');
        $dto->producto_id = (int)$dto->int('producto_id', 0);
        $dto->cantidad = (int)$dto->int('cantidad', 0);
        $dto->costo_unitario = (float)$dto->num('costo_unitario', 0);
        $dto->notas = $dto->str('notas');
        $dto->required('proveedor', $dto->proveedor, 'Indica el proveedor.');
        if ($dto->producto_id <= 0) $dto->fail('producto_id', 'Elige un producto.');
        if ($dto->cantidad <= 0) $dto->fail('cantidad', 'La cantidad debe ser mayor a cero.');
        if ($dto->costo_unitario < 0) $dto->fail('costo_unitario', 'El costo no puede ser negativo.');
        $dto->check();
        return $dto;
    }
}
