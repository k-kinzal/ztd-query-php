<?php

declare(strict_types=1);

namespace SqlCatalog\Php;

use PhpParser\Node\Expr;
use SqlCatalog\Type\TypeShape;

/**
 * One declared parameter of a function or method.
 *
 * @visibility root
 */
final class ParameterShape
{
    /**
     * @param string $name The parameter name without its sigil
     * @param TypeShape $type The declared type
     * @param Expr|null $default The default value expression, when the parameter has one
     * @param bool $variadic Whether the parameter collects the remaining arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly TypeShape $type,
        public readonly ?Expr $default = null,
        public readonly bool $variadic = false,
    ) {
    }
}
