<?php
namespace App\Entities;

use App\Utils\Url;

class ProductoEntity
{
    public function __construct(
        public int $id,
        public ?int $categoria_id,
        public ?string $categoria,
        public string $nombre,
        public ?string $descripcion,
        public float $precio,
        public float $costo,
        public ?string $imagen,
        public string $imagen_posicion,
        public bool $disponible,
        public ?string $agotado_motivo,
        public bool $controla_stock,
        public int $stock,
        public int $stock_minimo,
        public bool $destacado,
    ) {
    }

    public static function fromRow(array $r): self
    {
        return new self(
            (int)$r['id'],
            $r['categoria_id'] !== null ? (int)$r['categoria_id'] : null,
            $r['categoria'] ?? null,
            $r['nombre'],
            $r['descripcion'] ?? null,
            round((float)$r['precio'], 2),
            round((float)($r['costo'] ?? 0), 2),
            Url::asset($r['imagen'] ?? null),
            $r['imagen_posicion'] ?: '50% 50%',
            (bool)$r['disponible'],
            $r['agotado_motivo'] ?? null,
            (bool)($r['controla_stock'] ?? 0),
            (int)($r['stock'] ?? 0),
            (int)($r['stock_minimo'] ?? 0),
            (bool)($r['destacado'] ?? 0),
        );
    }

    public function stockBajo(): bool
    {
        return $this->controla_stock && $this->stock <= $this->stock_minimo;
    }

    /** Vista para el personal (incluye costo, stock y margen). */
    public function toArray(): array
    {
        $a = get_object_vars($this);
        $a['stock_bajo'] = $this->stockBajo();
        $a['margen'] = $this->precio > 0 ? round(($this->precio - $this->costo) / $this->precio * 100, 1) : 0;
        return $a;
    }

    /** Vista pública para el menú del cliente: sin costo ni stock exacto. */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'categoria_id' => $this->categoria_id,
            'categoria' => $this->categoria,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'precio' => $this->precio,
            'imagen' => $this->imagen,
            'imagen_posicion' => $this->imagen_posicion,
            'disponible' => $this->disponible,
            'destacado' => $this->destacado,
        ];
    }
}
