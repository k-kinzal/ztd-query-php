<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\RollupItems;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Resolves a scalar subquery that the server evaluates as its selected expression.
 *
 * Rule: MYSQL-SCALAR-REDUCTION-001. A SELECT with one expression and no input table, filter, aggregate or window can be
 * reduced to that bound expression. In this form MySQL ignores LIMIT and OFFSET, including
 * LIMIT 0; the expression retains its NULL attribute. The query is still derived before
 * reduction so its diagnostic and binding checks run. Verified through SQL on MySQL 5.6.51
 * and 8.4.7; no server implementation is used. A direct column is not reduced in MySQL
 * 5.x. A tableless ROLLUP is retained through 8.4 and reduced in 9.1, with its nullable
 * result attribute preserved; verified through the same statement on both releases.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/scalar-subqueries.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ScalarReduction
{
    /**
     * Answers the existing selected expression when the scalar wrapper can be eliminated.
     */
    public function expression(Query $query, GrammarRelease $release = GrammarRelease::MySql847): ?Scalar
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        if (!$query instanceof Select || ($query->from !== null && !$query->from instanceof Dual) || $query->where !== null || $query->having !== null || $query->qualify !== null || $query->into !== null || count($query->items) !== 1 || (new RollupItems())->windowed($query) || ($release !== GrammarRelease::MySql910 && (new RollupItems())->rolls($query))) {
            return null;
        }
        $item = $query->items[0];
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);

        return $item instanceof SelectExpression && !($legacy && $item->expression instanceof ColumnUse) && !(new Aggregation())->aggregates([$item->expression]) ? $item->expression : null;
    }

    /**
     * Tells whether a scalar result retains the selected expression's NULL attribute.
     *
     * With no table or filter, aggregate and window results retain that attribute too.
     * From MySQL 5.7, LIMIT can make the result empty and nullable; MySQL 5.6 retains the
     * attribute even when LIMIT discards the row. This describes the published result
     * metadata, including that legacy behavior, rather than a minimum row count.
     */
    public function preservesNullability(Query $query, bool $legacy = false): bool
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            if ($query instanceof QueryExpression && !$legacy && $query->limit !== null) {
                return false;
            }
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }

        return $query instanceof Select && ($query->from === null || $query->from instanceof Dual) && $query->where === null && $query->having === null && $query->qualify === null && ($legacy || $query->limit === null);
    }
}
