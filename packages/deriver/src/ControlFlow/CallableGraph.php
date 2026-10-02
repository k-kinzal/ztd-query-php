<?php

declare(strict_types=1);

namespace Deriver\ControlFlow;

use Deriver\Reference\SourceRef;

/**
 * A callable control-flow graph independent of the PHP parser.
 *
 * @visibility root
 */
final class CallableGraph
{
    /**
     * @param string $symbol symbol
     * @param list<Parameter> $parameters parameters
     * @param array<int, BasicBlock> $blocks blocks
     * @param SourceRef $source source
     * @param string $returnType returnType
     * @param bool $byReference byReference
     * @param bool $strict strict
     * @param string $className className
     * @param array<string, bool> $captures captures
     * @param array<int, ExceptionRegion> $regions regions
     * @param bool $allowExtraArguments Whether surplus positional arguments are legal
     * @param string $visibility Declared method visibility
     * @param bool $static Whether a method has no instance binding
     * @param bool $abstract Whether the declaration has no executable method body
     * @param bool $external Whether an external declaration supplies a signature without its implementation
     * @param string $docComment Raw declaration doc comment, or an empty string
     */
    public function __construct(
        public readonly string $symbol,
        public readonly array $parameters,
        public readonly array $blocks,
        public readonly SourceRef $source,
        public readonly string $returnType = 'mixed',
        public readonly bool $byReference = false,
        public readonly bool $strict = false,
        public readonly string $className = '',
        public readonly array $captures = [],
        public readonly array $regions = [],
        public readonly bool $allowExtraArguments = true,
        public readonly string $visibility = 'public',
        public readonly bool $static = false,
        public readonly bool $abstract = false,
        public readonly bool $external = false,
        public readonly string $docComment = '',
    ) {
    }
}
