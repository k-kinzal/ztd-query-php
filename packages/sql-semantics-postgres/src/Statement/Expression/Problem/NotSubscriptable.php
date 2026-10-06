<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A subscript applied to a value of a type without subscripting.
 *
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-SUBSCRIPTS.
 *
 * @visibility public
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotSubscriptable('integer'))->message() // => 'Cannot subscript type integer because it does not support subscripting.'
 */
final class NotSubscriptable implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $type The type of the value
     */
    public function __construct(public readonly string $type)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Cannot subscript type ' . $this->type . ' because it does not support subscripting.';
    }
}
