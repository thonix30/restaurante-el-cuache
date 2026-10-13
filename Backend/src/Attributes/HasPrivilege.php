<?php
namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class HasPrivilege
{
    public function __construct(
        public string $privilege
    ) {
    }
}
