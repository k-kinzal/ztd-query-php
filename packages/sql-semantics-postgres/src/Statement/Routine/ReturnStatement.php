<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * `RETURN expression` in an SQL-standard function body: the value the function returns.
 *
 * Mirrors PostgreSQL's `ReturnStmt`. It is written as the whole body or as a
 * statement of a `BEGIN ATOMIC` block; the expression sees the parameters.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Reading the returned expression
 *     $return = new \SqlSemantics\Platform\PostgreSql\Statement\Routine\ReturnStatement(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral(true));
 *     $return->value->value // => true
 */
final class ReturnStatement implements Clause
{
    use Snapshot;

    /**
     * @param Scalar $value The returned expression
     */
    public function __construct(public readonly Scalar $value)
    {
    }

    /**
     * Derives the returned expression in the environment of the body.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->value, $environment);
    }

    /**
     * Writes RETURN and the expression.
     */
    public function render(Output $out): void
    {
        $out->keyword('RETURN')->node($this->value);
    }
}
