<?php
namespace App\Controllers;

use App\Attributes\Authorize;
use App\Attributes\Route;
use App\Core\Controller;
use App\Core\HttpException;
use App\DTOs\ProductoDTO;
use App\Services\CategoriaService;
use App\Services\MesaService;
use App\Services\ProductoService;
use App\Utils\Auth;
use App\Utils\Config;
use App\Utils\Audit;
use App\Utils\Storage;
use App\Utils\Url;

/** Productos, categorías, mesas, configuración y subida de imágenes. */
#[Authorize(['Admin'])]
class CatalogoController extends Controller
{
    // ── Productos ───────────────────────────────────────────────────────
    #[Route('/productos', 'GET')]
    #[Authorize]
    public function productos()
    {
        $this->ok((new ProductoService())->list([
            'q' => $this->query('q'),
            'categoria_id' => $this->query('categoria_id'),
            'solo_agotados' => $this->query('solo_agotados') === '1',
        ]));
    }

    #[Route('/productos', 'POST')]
    public function crearProducto()
    {
        $this->ok((new ProductoService())->create(ProductoDTO::fromRequest($this->input())), 'Platillo creado', 201);
    }

    #[Route('/productos/{id}/disponibilidad', 'PUT')]
    #[Authorize(['Admin', 'Cocinero'])]
    public function disponibilidad(string $id)
    {
        $stock = $this->input('stock');
        $this->ok((new ProductoService())->setDisponibilidad((int)$id, (bool)$this->input('disponible'), $stock !== null ? (int)$stock : null));
    }

    #[Route('/productos/{id}', 'PUT')]
    public function editarProducto(string $id)
    {
        $this->ok((new ProductoService())->update((int)$id, ProductoDTO::fromRequest($this->input())), 'Platillo actualizado');
    }

    #[Route('/productos/{id}', 'DELETE')]
    public function borrarProducto(string $id)
    {
        (new ProductoService())->delete((int)$id);
        $this->ok(null, 'Platillo eliminado');
    }

    // ── Categorías ──────────────────────────────────────────────────────
    #[Route('/categorias', 'GET')]
    #[Authorize]
    public function categorias()
    {
        $this->ok((new CategoriaService())->list());
    }

    #[Route('/categorias', 'POST')]
    public function crearCategoria()
    {
        $this->ok((new CategoriaService())->save(null, $this->input()), 'Categoría creada', 201);
    }

    #[Route('/categorias/orden', 'PUT')]
    public function ordenCategorias()
    {
        (new CategoriaService())->reorder((array)$this->input('ids', []));
        $this->ok((new CategoriaService())->list());
    }

    #[Route('/categorias/{id}', 'PUT')]
    public function editarCategoria(string $id)
    {
        $this->ok((new CategoriaService())->save((int)$id, $this->input()), 'Categoría actualizada');
    }

    #[Route('/categorias/{id}', 'DELETE')]
    public function borrarCategoria(string $id)
    {
        (new CategoriaService())->delete((int)$id);
        $this->ok(null, 'Categoría eliminada');
    }

    // ── Mesas y QR ──────────────────────────────────────────────────────
    #[Route('/mesas', 'GET')]
    #[Authorize]
    public function mesas()
    {
        $this->ok((new MesaService())->list(Auth::isAdmin(), $this->query('todas') !== '1'));
    }

    #[Route('/mesas', 'POST')]
    public function crearMesa()
    {
        $this->ok((new MesaService())->save(null, $this->input()), 'Mesa creada', 201);
    }

    #[Route('/mesas/posiciones', 'PUT')]
    public function posiciones()
    {
        (new MesaService())->savePositions((array)$this->input('mesas', []));
        $this->ok(null, 'Plano guardado');
    }

    #[Route('/mesas/{id}', 'PUT')]
    public function editarMesa(string $id)
    {
        $this->ok((new MesaService())->save((int)$id, $this->input()), 'Mesa actualizada');
    }

    #[Route('/mesas/{id}', 'DELETE')]
    public function borrarMesa(string $id)
    {
        (new MesaService())->delete((int)$id);
        $this->ok(null, 'Mesa eliminada');
    }

    #[Route('/mesas/{id}/qr', 'POST')]
    public function regenerarQr(string $id)
    {
        $this->ok((new MesaService())->regenerarToken((int)$id), 'Nuevo código QR generado');
    }

    // ── Configuración ───────────────────────────────────────────────────
    #[Route('/configuracion', 'GET')]
    public function configuracion()
    {
        $this->ok(Config::settings());
    }

    #[Route('/configuracion', 'PUT')]
    public function guardarConfiguracion()
    {
        $permitidas = ['restaurante_nombre', 'restaurante_lema', 'url_base_menu', 'propina_sugerida', 'horas_max_semana',
            'descanso_min_horas', 'tolerancia_minutos', 'cobertura_minima', 'alerta_doc_dias', 'horario_apertura', 'horario_cierre'];
        $cambios = [];
        foreach ($this->input() as $k => $v) {
            if (!in_array($k, $permitidas, true)) continue;
            $v = mb_substr(trim((string)$v), 0, 500);
            if ($k === 'url_base_menu' && $v !== '' && !filter_var($v, FILTER_VALIDATE_URL)) {
                throw HttpException::invalid('La URL del menú no es válida.', ['errors' => ['url_base_menu' => 'URL inválida.']]);
            }
            Config::setSetting($k, $v);
            $cambios[$k] = $v;
        }
        if ($cambios) Audit::log('configuracion_cambiada', 'sistema', 'configuracion', null, 'Ajustes: ' . implode(', ', array_keys($cambios)), $cambios);
        $this->ok(Config::settings(), 'Configuración guardada');
    }

    // ── Imágenes públicas (platillos y fotos de perfil) ─────────────────
    #[Route('/uploads/imagen', 'POST')]
    public function subirImagen()
    {
        if (empty($_FILES['imagen'])) throw HttpException::invalid('No se recibió ninguna imagen.');
        $path = Storage::saveUploadedImage($_FILES['imagen'], 'uploads');
        $this->ok(['path' => $path, 'url' => Url::asset($path)], 'Imagen subida', 201);
    }
}
