<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SplObjectStorage;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\LateOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Statement\Node;

/**
 * Refuses query graphs whose features require disjoint grammar generations.
 *
 * Rule: MYSQL-QUERY-GENERATIONS-001. PROCEDURE ANALYSE, SQL_CACHE and the
 * legacy trailing-clause forms end before 8.0. WITH, VALUES, TABLE, window
 * specifications, JSON_TABLE, QUALIFY, INTERSECT and EXCEPT start in 8.x or later. A
 * graph containing both sets cannot belong to any supported release.
 * This is a constructor invariant; it does not decide whether a statement
 * executes or whether a particular release accepts every feature.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/mysql-nutshell.html,
 * https://dev.mysql.com/doc/refman/8.0/en/with.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-operations.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Generations
{
    /**
     * Checks all nested operands, including subqueries and common tables.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When no grammar generation can contain the combination
     */
    public function check(Node $root): void
    {
        $pending = [$root];
        $seen = new SplObjectStorage();
        $requirements = 0;
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif (is_object($value)) {
                if (isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;
                $requirements |= $this->requirement($value);
                Check::input($requirements !== 3, 'A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');
                if ($value instanceof Node) {
                    array_push($pending, ...array_values(get_object_vars($value)));
                }
            }
        }
    }

    /**
     * Answers a legacy requirement (1), a modern requirement (2), or neither (0).
     */
    public function requirement(object $value): int
    {
        if ($value instanceof ProcedureAnalyse || $value instanceof LateOrdering || $value instanceof LeadingUnion || $value instanceof OrderedSetOperation || $value === SelectOption::Cache) {
            return 1;
        }
        if ($value instanceof WithClause || $value instanceof CommonTableExpression || $value instanceof ValuesQuery || $value instanceof ExplicitTable || $value instanceof WindowSpecification || $value instanceof JsonTable || ($value instanceof Select && $value->qualify !== null) || $value === SetOperator::Intersect || $value === SetOperator::Except) {
            return 2;
        }

        return 0;
    }
}
