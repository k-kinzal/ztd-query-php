<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A relation name that several visible relations of one query level answer to.
 *
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-TABLE-ALIASES.
 *
 * @visibility public
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\AmbiguousRelation('t'))->message() // => 'Table reference t is ambiguous.'
 */
final class AmbiguousRelation implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $name The relation name
     */
    public function __construct(public readonly string $name)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Table reference ' . $this->name . ' is ambiguous.';
    }
}
