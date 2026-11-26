<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Sublinks;

/**
 * A membership test against the rows of a query: `a [NOT] IN (SELECT …)`.
 *
 * Mirrors PostgreSQL's `SubLink` node of kind `ANY_SUBLINK` with the
 * operator `=`; NOT IN is the negation of that test.
 *
 * Rule: PG-IN-SUBQUERY-001. The query is derived in the environment of the
 * expression; it returns one column, or as many as the fields of a row
 * constructor on the left. Facts: the type of `=` over the value and the
 * column (PG-OPERATOR-TYPING-001); NULL when no row matches and a compared
 * value is NULL, so the result can be NULL. The value must keep its place
 * before IN (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-subquery.html#FUNCTIONS-SUBQUERY-IN. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class InSubquery implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested value
     * @param bool $negated Whether NOT is written
     * @param Query $query The query inside the parentheses
     */
    public function __construct(public readonly Scalar $operand, public readonly bool $negated, public readonly Query $query)
    {
        Check::input((new Precedence())->before($operand, Precedence::PATTERN), 'The tested value needs parentheses to keep its place.');
    }

    /**
     * Derives the value and the query, and the comparison of the value with its rows.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $fact = $derivation->query($this->query, $environment);

        return new ScalarFact((new Sublinks())->compared($derivation, $operand->type, '=', $fact), Nullability::Nullable);
    }

    /**
     * Writes the value, [NOT] IN and the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('IN')->symbol('(')->node($this->query)->symbol(')');
    }
}
