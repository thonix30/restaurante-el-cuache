<?php
namespace App\DTOs;

/**
 * Envío de platillos a cocina. Precios y totales NUNCA se toman del cliente:
 * solo ids, cantidades y notas.
 */
class PedidoDTO extends DTO
{
    /** @var array<int, array{producto_id:int, cantidad:int, notas:?string}> */
    public array $items = [];
    public ?int $mesa_id;
    public ?int $personas;

    public static function fromRequest(array $data): self
    {
        $dto = new self($data);
        $dto->mesa_id = $dto->int('mesa_id');
        $dto->personas = $dto->int('personas');

        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        foreach ($items as $it) {
            $pid = (int)($it['producto_id'] ?? $it['id'] ?? 0);
            $cant = (int)($it['cantidad'] ?? 0);
            if ($pid <= 0 || $cant <= 0) continue;
            if ($cant > 50) $cant = 50;
            $notas = isset($it['notas']) ? mb_substr(trim((string)$it['notas']), 0, 255) : null;
            $dto->items[] = ['producto_id' => $pid, 'cantidad' => $cant, 'notas' => $notas ?: null];
        }
        if (!$dto->items) $dto->fail('items', 'El pedido no tiene platillos.');
        if (count($dto->items) > 60) $dto->fail('items', 'El pedido es demasiado grande.');
        $dto->check();
        return $dto;
    }
}
