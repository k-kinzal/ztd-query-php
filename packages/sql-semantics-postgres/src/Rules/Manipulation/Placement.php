<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\ModifyingCommonTables;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\DefaultRequest;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\MergeAction;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Modification;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use UnitEnum;

/**
 * Checks the values that only some positions of a statement admit: DEFAULT, MERGE_ACTION() and SELECT INTO.
 *
 * Rule: PG-PLACEMENT-001. DEFAULT is a value only where a statement
 * assigns it to a column: a value of the VALUES rows of an INSERT or of the
 * INSERT action of MERGE, the value of a SET item, or a field of the row
 * constructor of a multiple-column SET item; anywhere else in the statement
 * it is reported. MERGE_ACTION() is admitted only in the RETURNING list of
 * MERGE, subqueries included. A data-modifying statement checks its own
 * parts; a data-modifying statement nested in it checks its own. A
 * selection with INTO inside a statement that is not a query statement is
 * reported. A WITH clause holding a data-modifying statement is reported
 * anywhere but at the top level of the query a statement runs
 * (PG-MODIFYING-CTE-001). Termination: the structure is a finite tree walked with a stack.
 * Source: https://www.postgresql.org/docs/17/sql-insert.html, https://www.postgresql.org/docs/17/functions-merge-support.html,
 * https://www.postgresql.org/docs/17/sql-selectinto.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Placement
{
    /**
     * Answers the structure objects reachable from a root, the root included; data-modifying statements below the root are entered only when asked.
     *
     * @return list<object>
     */
    public function objects(object $root, bool $nested): array
    {
        $objects = [];
        $pending = [$root];
        while ($pending !== []) {
            $current = array_pop($pending);
            $objects[] = $current;
            foreach (get_object_vars($current) as $value) {
                foreach (is_array($value) ? $value : [$value] as $item) {
                    if (is_object($item) && !$item instanceof UnitEnum && ($nested || !$item instanceof Modification)) {
                        $pending[] = $item;
                    }
                }
            }
        }

        return $objects;
    }

    /**
     * Reports the DEFAULT values and MERGE_ACTION() calls of a statement that stand where they are not admitted.
     *
     * @param list<DefaultRequest> $defaults The DEFAULT values at admitted positions
     * @param list<object> $actions The objects inside which MERGE_ACTION() is admitted
     */
    public function values(Node $statement, array $defaults, array $actions, Derivation $derivation): void
    {
        $admitted = [];
        foreach ($actions as $action) {
            foreach ($this->objects($action, false) as $object) {
                $admitted[] = $object;
            }
        }
        foreach ($this->objects($statement, false) as $object) {
            if ($object instanceof DefaultRequest && !in_array($object, $defaults, true)) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::DefaultPlacement));
            }
            if ($object instanceof MergeAction && !in_array($object, $admitted, true)) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::MergeActionPlacement));
            }
        }
    }

    /**
     * Reports the WITH clauses holding a data-modifying statement inside a statement, except the top-level one of the query it runs, and answers that one.
     */
    public function modifying(Node $root, Query $query, Derivation $derivation): ?WithClause
    {
        $top = $query instanceof Modification ? $query->commonTables() : (new ModifyingCommonTables())->top($query);
        $top = $top instanceof WithClause ? $top : null;
        (new ModifyingCommonTables())->check($root, $top, $derivation);

        return $top;
    }

    /**
     * Reports every selection with INTO inside a statement, except the one the statement admits.
     */
    public function into(Node $root, Derivation $derivation, ?Select $admitted = null): void
    {
        foreach ($this->objects($root, true) as $object) {
            if ($object instanceof Select && $object->into !== null && $object !== $admitted) {
                $derivation->report(new QueryMisuse(QueryMisuseRule::IntoNotAllowed));
            }
        }
    }
}
