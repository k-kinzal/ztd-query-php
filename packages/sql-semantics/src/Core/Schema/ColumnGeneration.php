<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Schema;

use InvalidArgumentException;
use SqlSemantics\Statement\Element;

/**
 * A typed generation clause, including identity options or its computed expression.
 * @visibility public
 * @example Accepting generated column facts
 *     $accept = static fn (\SqlSemantics\Core\Schema\ColumnGeneration $value): string => $value->kind->value;
 *     $accept instanceof \Closure // => true
 */
final class ColumnGeneration
{
    /**
     * An identity has no computation expression; virtual and stored columns do.
     * @throws InvalidArgumentException When supplied state violates its invariants
     */
    public function __construct(
        public readonly GenerationKind $kind,
        public readonly Element $clause,
        public readonly ?Element $expression,
    ) {
        Invariant::ensure(($kind === GenerationKind::Identity) === ($expression === null), 'Only an identity generation has no computation expression.');
        Invariant::elements($clause, $expression);
    }
}
