<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * An expression written in parentheses: `( a_expr )`.
 *
 * The raw parser of PostgreSQL keeps no node for the parentheses, but they
 * fix the evaluation structure of what they enclose, so the model keeps them
 * as written; rendering never adds or removes a grouping.
 *
 * Rule: PG-GROUPED-001. The type, the NULL fact and the output name are those
 * of the operand; the name resolution stays with the operand. A query in
 * parentheses is a scalar subquery, and more parentheses around it belong to
 * the query, so a grouping never encloses a scalar subquery.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html. Status: Implemented.
 *
 * @visibility public
 * @example Keeping the parentheses written
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT (1 + 2) * 3')->toString() // => 'SELECT (1 + 2) * 3'
 */
final class Grouped implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $operand The enclosed expression; not a scalar subquery
     */
    public function __construct(public readonly Scalar $operand)
    {
        Check::input(!$operand instanceof ScalarSubquery, 'Parentheses around a scalar subquery belong to its query.');
    }

    /**
     * Names an unaliased result column as the operand does.
     */
    public function outputName(): ?Name
    {
        return (new ColumnNaming())->name($this->operand);
    }

    /**
     * Derives the operand; the grouping has its type and NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);

        return new ScalarFact($fact->type, $fact->nullability);
    }

    /**
     * Writes the operand in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->operand)->symbol(')');
    }
}
