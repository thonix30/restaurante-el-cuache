<?php
namespace App\Attributes;

use Attribute;

/**
 * Exige sesión válida. Con roles vacíos basta con estar autenticado;
 * con roles, el usuario debe tener uno de ellos.
 * Se puede poner en la clase (aplica a todos sus métodos) o en el método
 * (tiene prioridad sobre el de la clase).
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Authorize
{
    public function __construct(
        public array $roles = []
    ) {
    }
}
