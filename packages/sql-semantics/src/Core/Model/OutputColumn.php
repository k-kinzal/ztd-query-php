<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Model;

/**
 * An ordered result column; duplicate output names remain separate positions.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Core\Model\OutputColumn $value): string => $value::class;
 *     $consume instanceof \Closure // => true
 *
 * @visibility public
 */
final class OutputColumn
{
    /**
     * @param int $ordinal Zero-based result position
     * @param string|null $name Explicit alias or direct column name; null for an engine-generated label
     * @param Expression $expression Typed result expression
     */
    public function __construct(
        public readonly int $ordinal,
        public readonly ?string $name,
        public readonly Expression $expression,
    ) {
    }
}
