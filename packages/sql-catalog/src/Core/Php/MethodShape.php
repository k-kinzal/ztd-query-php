<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Php;

use PhpParser\Node\FunctionLike;
use SqlCatalog\Core\Type\TypeShape;

/**
 * One method the analyzer can look up and, when it is worth it, step into.
 *
 * @visibility root
 */
final class MethodShape
{
    /**
     * @param string $className The declaring class, fully qualified without a leading backslash
     * @param string $name The method name as written
     * @param list<ParameterShape> $parameters The declared parameters, in order
     * @param TypeShape $returnType The declared return type
     * @param bool $static Whether the method is declared static
     * @param FunctionLike|null $node The declaration, when its body is available to step into
     * @param string $file The file the declaration was read from
     */
    public function __construct(
        public readonly string $className,
        public readonly string $name,
        public readonly array $parameters,
        public readonly TypeShape $returnType,
        public readonly bool $static = false,
        public readonly ?FunctionLike $node = null,
        public readonly string $file = '',
    ) {
    }

    /**
     * The method named the way findings refer to it.
     */
    public function qualifiedName(): string
    {
        return $this->className . '::' . $this->name;
    }
}
