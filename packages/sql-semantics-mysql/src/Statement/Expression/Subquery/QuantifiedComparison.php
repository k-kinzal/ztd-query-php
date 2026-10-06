<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A comparison with every or any row of a subquery: `x op ALL|ANY (SELECT …)` (`Item_allany_subselect`).
 *
 * It belongs to the bool_pri level like the plain comparisons and takes a
 * bool_pri operand (MYSQL-PRECEDENCE-001).
 *
 * Rule: MYSQL-QUANTIFIED-COMPARISON-001. Facts: 1, 0 or NULL, an integer;
 * NULL fact by MYSQL-SUBQUERY-ROWS-001 (`<=>` with ALL or ANY is still NULL
 * when no row decides). Terminates: the operand and the query are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/any-in-some-subqueries.html,
 * https://dev.mysql.com/doc/refman/8.4/en/all-subqueries.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Comparing with every row
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a >= ALL (SELECT 1)');
 *     [$query->statement->where->operator->value, $query->statement->where->quantifier->value] // => ['>=', 'ALL']
 */
final class QuantifiedComparison implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The compared expression
     * @param ComparisonOperator $operator The comparison operator
     * @param Quantifier $quantifier Whether every row or any row must satisfy the comparison
     * @param Query $query The query whose rows are compared with
     */
    public function __construct(public readonly Scalar $operand, public readonly ComparisonOperator $operator, public readonly Quantifier $quantifier, public readonly Query $query)
    {
        Check::input((new Precedence())->fits($operand, Precedence::BOOL_PRI, Precedence::BOOL_PRI), 'The operand of a quantified comparison needs a grouping to keep its place.');
    }

    /**
     * Derives the operand and the query and the NULL fact of the comparison.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $query = $derivation->query($this->query, $environment);

        return (new Operands())->truth((new SubqueryRows())->test($operand, $query, $derivation));
    }

    /**
     * Writes the operand, the operator, the quantifier and the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->symbol($this->operator->value)->keyword($this->quantifier->value)->symbol('(')->node($this->query)->symbol(')');
    }
}
