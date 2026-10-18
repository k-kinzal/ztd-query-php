<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A membership test in the rows of a subquery: `x [NOT] IN (SELECT …)` (`Item_in_subselect`).
 *
 * The operand is a bit_expr (MYSQL-PRECEDENCE-001).
 *
 * Rule: MYSQL-IN-QUERY-001. Facts: 1, 0 or NULL, an integer; NULL fact by
 * MYSQL-SUBQUERY-ROWS-001; the operand must have as many columns as the
 * subquery. Terminates: the operand and the query are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/any-in-some-subqueries.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Testing membership in a subquery
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a NOT IN (SELECT 1)');
 *     $query->statement->query->where->negated // => true
 * @example Refusing membership in a fixed zero-column output
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])]);
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), $query) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class InQuery implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand $operand The tested expression
     * @param Query $query The query whose rows are searched
     * @param bool $negated Whether NOT is written before IN
     */
    public function __construct(public readonly Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand $operand, public readonly Query $query, public readonly bool $negated = false)
    {
        Check::input((new Precedence())->fits($operand, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The operand of IN needs a grouping to keep its place.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes())->query($operand, $query) === null, 'Compared operand and subquery have compatible row shapes.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($query) === null, 'An ordinary subquery predicate has coherent fixed read columns.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions())->check($query);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the operand and the query and the NULL fact of the test.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        (new SubqueryRows())->checkLimit($this->query, $derivation);
        $operand = $derivation->scalar($this->operand, $environment);
        $query = $derivation->query($this->query, $environment);

        return (new SubqueryRows())->predicate($operand, $query, $derivation, $this, $this->negated);
    }

    /**
     * Writes the operand, the optional NOT, IN and the query in parentheses.
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
