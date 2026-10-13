<?php
namespace App\Attributes;

use Attribute;

/**
 * Marca un método como público aunque su controlador tenga #[Authorize].
 * Si llega un token válido, igual queda disponible en Auth::user().
 */
#[Attribute(Attribute::TARGET_METHOD)]
class PublicRoute
{
}
