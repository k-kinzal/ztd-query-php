<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\ScalarReduction;
use SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A subquery used as a value: `(SELECT …)`, a scalar or row subquery (`Item_singlerow_subselect`).
 *
 * The subquery sees the enclosing positions as its outer scope.
 *
 * Rule: MYSQL-SCALAR-SUBQUERY-001. Facts: MYSQL-SUBQUERY-ROWS-001; a
 * a one-column subquery has that column's type and is NULL when no row is
 * returned. Explicit multi-column output uses RowSubquery instead. Wildcard
 * output remains a declaration-dependent width constraint. A tableless, unfiltered row subquery
 * retains its columns' combined NULL attribute, subject to the release's LIMIT rule.
 * MySQL 5.6 instead reports a nonnullable attribute for tableless unfiltered rows and
 * tableless UNION rows, independently of NULL values in their columns.
 * A reducible one-expression query
 * instead publishes that bound expression as its replacement and retains its
 * type and NULL attribute (MYSQL-SCALAR-REDUCTION-001). Terminates: the query is a
 * strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/scalar-subqueries.html,
 * https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 * Status: Implemented.
 *
 * @example Refusing a fixed row as a scalar subquery
 *     $items = [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')), new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'))];
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], $items)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @example Refusing a tableless star as an ordinary scalar subquery
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()]);
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery($query) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing inconsistent written rows as an ordinary scalar subquery
 *     $one = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1');
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([$one]), new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3')])]);
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery($query) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @visibility public
 * @example Comparing with a scalar subquery
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = (SELECT 1)');
 *     $query->statement->query->where->right instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery // => true
 * @example Refusing an INTO destination after an expression subquery
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]);
 *     $destination = new \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDumpfile(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('file'));
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery(new \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement($query, into: $destination, intoPosition: \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterQuery)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ScalarSubquery implements Scalar
{
    use Snapshot;

    /**
     * @param Query $query The query
     */
    public function __construct(public readonly Query $query)
    {
        \SqlSemantics\Diagnostic\Check::input(((new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryWidth())->of($query) ?? 1) === 1, 'A scalar subquery has one output column when its width is fixed.');
        \SqlSemantics\Diagnostic\Check::input((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($query) === null, 'An ordinary subquery operand has coherent fixed read columns.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions())->check($query);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the query with the enclosing position as outer scope, and the value it yields.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $query = $derivation->query($this->query, $environment);
        $value = (new SubqueryRows())->value($query, $derivation->facts());
        $release = $derivation->context->profile->grammar;
        $domain = (new \SqlSemantics\Platform\MySql\Rules\Typing\Precision())->domain($value->type);
        if ($domain?->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer) {
            $value = new ScalarFact(new \SqlSemantics\Statement\Type\Known($domain->withStorageField(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong)), $value->nullability);
        }
        $reduction = new ScalarReduction();
        $replacement = $reduction->expression($this->query, $derivation->context->profile->grammar);
        if ($replacement === null) {
            return new ScalarFact($value->type, (new SubqueryRows())->attribute($this->query, $query, $release));
        }

        return count($query->shape->slots) !== 1 ? $value : new ScalarFact($query->shape->slots[0]->type, $query->shape->slots[0]->nullability, replacement: $replacement);
    }

    /**
     * Writes the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->query)->symbol(')');
    }
}
