<?php
namespace App\DTOs;

class PagoDTO extends DTO
{
    public float $monto;
    public float $propina;
    public string $metodo;
    public ?float $recibido;
    public ?string $etiqueta;
    public ?array $items;

    public static function fromRequest(array $data): self
    {
        $dto = new self($data);
        $dto->monto = (float)$dto->num('monto', 0);
        $dto->propina = max(0, (float)$dto->num('propina', 0));
        $dto->metodo = $dto->oneOf('metodo', ['Efectivo', 'Tarjeta', 'Transferencia'], 'Efectivo');
        $dto->recibido = $dto->num('recibido');
        $dto->etiqueta = $dto->str('etiqueta', 60);
        $dto->items = null;
        if (is_array($data['items'] ?? null)) {
            $dto->items = [];
            foreach ($data['items'] as $it) {
                $id = (int)($it['item_id'] ?? 0);
                $c = (float)($it['cantidad'] ?? 0);
                if ($id > 0 && $c > 0) $dto->items[] = ['item_id' => $id, 'cantidad' => round($c, 3)];
            }
        }
        if ($dto->monto <= 0) $dto->fail('monto', 'El monto debe ser mayor a cero.');
        if ($dto->metodo === 'Efectivo' && $dto->recibido !== null && $dto->recibido + 0.005 < $dto->monto + $dto->propina) {
            $dto->fail('recibido', 'El efectivo recibido no alcanza para cubrir el cobro.');
        }
        $dto->check();
        return $dto;
    }
}
