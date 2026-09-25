<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Php;

use PhpParser\Node\FunctionLike;
use SqlCatalog\Core\Type\TypeShape;

/**
 * One free function the analyzer can look up and, when it is worth it, step into.
 *
 * @visibility root
 */
final class FunctionShape
{
    /**
     * @param string $name The function name, fully qualified without a leading backslash
     * @param list<ParameterShape> $parameters The declared parameters, in order
     * @param TypeShape $returnType The declared return type
     * @param FunctionLike|null $node The declaration, when its body is available to step into
     * @param string $file The file the declaration was read from
     */
    public function __construct(
        public readonly string $name,
        public readonly array $parameters,
        public readonly TypeShape $returnType,
        public readonly ?FunctionLike $node = null,
        public readonly string $file = '',
    ) {
    }
}
