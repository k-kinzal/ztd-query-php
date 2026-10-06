<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query\Facts;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Modification;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause;
use SqlSemantics\Statement\Node;
use SqlSemantics\Validation\ValueGraph;

/**
 * Reports data-modifying common table expressions that are not in the WITH clause of the statement itself.
 *
 * Rule: PG-MODIFYING-CTE-001. A common table computed by INSERT, UPDATE,
 * DELETE or MERGE is allowed only in the WITH clause that belongs to the
 * top level of a statement: the WITH of a query statement (through grouping
 * parentheses) or of a data-modifying statement. In a subquery, in a set
 * operation operand or in the statement of another common table it is
 * reported once per WITH clause. Terminates: one pass over the finite value graph.
 * Source: https://www.postgresql.org/docs/17/queries-with.html#QUERIES-WITH-MODIFYING. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ModifyingCommonTables
{
    /**
     * Reports every WITH clause of a statement other than its top-level one that holds a data-modifying common table.
     *
     * @param Node $root The statement root
     * @param WithClause|null $top The WITH clause that belongs to the top level of the statement
     */
    public function check(Node $root, ?WithClause $top, Derivation $derivation): void
    {
        foreach ((new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\PostgreSql\\Statement\\']))->objects($root) as $object) {
            if (!$object instanceof WithClause || $object === $top) {
                continue;
            }
            foreach ($object->tables as $table) {
                if ($table->query instanceof Modification) {
                    $derivation->report(new QueryMisuse(QueryMisuseRule::NestedModification));
                    break;
                }
            }
        }
    }

    /**
     * Answers the WITH clause that belongs to the top level of a query statement: the one written before its body, inside grouping parentheses.
     */
    public function top(Node $query): ?WithClause
    {
        while ($query instanceof ParenthesizedQuery || ($query instanceof QueryExpression && $query->with === null)) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }

        return $query instanceof QueryExpression ? $query->with : null;
    }
}
