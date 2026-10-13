<?php
namespace App\DTOs;

class ProductoDTO extends DTO
{
    public string $nombre;
    public ?int $categoria_id;
    public ?string $descripcion;
    public float $precio;
    public float $costo;
    public ?string $imagen;
    public string $imagen_posicion;
    public bool $disponible;
    public bool $controla_stock;
    public int $stock;
    public int $stock_minimo;
    public bool $destacado;

    public static function fromRequest(array $data): self
    {
        $dto = new self($data);
        $dto->nombre = (string)$dto->str('nombre', 150, '');
        $dto->categoria_id = $dto->int('categoria_id');
        $dto->descripcion = $dto->str('descripcion', 1000);
        $dto->precio = (float)$dto->num('precio', 0);
        $dto->costo = (float)$dto->num('costo', 0);
        $dto->imagen = $dto->str('imagen', 500);
        $pos = $dto->str('imagen_posicion', 20, '50% 50%');
        $dto->imagen_posicion = preg_match('/^\d{1,3}(\.\d+)?% \d{1,3}(\.\d+)?%$/', $pos) ? $pos : '50% 50%';
        $dto->disponible = $dto->bool('disponible', true);
        $dto->controla_stock = $dto->bool('controla_stock', false);
        $dto->stock = max(0, (int)$dto->int('stock', 0));
        $dto->stock_minimo = max(0, (int)$dto->int('stock_minimo', 5));
        $dto->destacado = $dto->bool('destacado', false);

        $dto->required('nombre', $dto->nombre, 'El platillo necesita un nombre.');
        if ($dto->precio <= 0) $dto->fail('precio', 'El precio debe ser mayor a cero.');
        if ($dto->costo < 0) $dto->fail('costo', 'El costo no puede ser negativo.');
        $dto->check();
        return $dto;
    }

    /** La imagen se guarda como ruta relativa (uploads/...) aunque llegue como URL absoluta. */
    public function imagenRelativa(): ?string
    {
        if (!$this->imagen) return null;
        if (preg_match('@/(uploads/[\w.\-]+)$@', $this->imagen, $m)) return $m[1];
        return $this->imagen;
    }
}
