<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Model;

/**
 * A result ordering expression with explicit direction and NULL placement.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Core\Model\Ordering $value): string => $value::class;
 *     $consume instanceof \Closure // => true
 *
 * @visibility public
 */
final class Ordering
{
    /**
     * @param Expression $expression Sort expression
     * @param bool $descending Descending order
     * @param bool|null $nullsFirst Explicit NULLS FIRST/LAST; null uses the dialect default
     */
    public function __construct(
        public readonly Expression $expression,
        public readonly bool $descending = false,
        public readonly ?bool $nullsFirst = null,
    ) {
    }
}
