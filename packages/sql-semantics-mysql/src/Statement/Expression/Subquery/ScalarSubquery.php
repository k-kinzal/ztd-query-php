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
 * subquery of one column has its type, one of several columns is a row,
 * and either is NULL when no row is returned. A tableless, unfiltered row subquery
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
 * @visibility public
 * @example Comparing with a scalar subquery
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = (SELECT 1)');
 *     $query->statement->where->right instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery // => true
 */
final class ScalarSubquery implements Scalar
{
    use Snapshot;

    /**
     * @param Query $query The query
     */
    public function __construct(public readonly Query $query)
    {
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
