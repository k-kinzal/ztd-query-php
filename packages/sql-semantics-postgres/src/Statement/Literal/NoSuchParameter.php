<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A parameter reference whose number no statement can bind.
 *
 * Parameters are numbered from 1; the server reports `$0` and numbers beyond
 * its limit as undefined parameters.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-PARAMETERS-POSITIONAL.
 *
 * @visibility public
 * @example Reporting parameter zero
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT $0');
 *     $query->field(0)->type->cause->message() // => 'There is no parameter $0.'
 */
final class NoSuchParameter implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $marker The parameter marker as the server reads it
     */
    public function __construct(public readonly string $marker)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'There is no parameter ' . $this->marker . '.';
    }
}
