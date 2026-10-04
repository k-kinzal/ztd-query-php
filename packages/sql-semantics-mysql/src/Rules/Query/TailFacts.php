<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;

/**
 * Derives the clauses written after a query: LIMIT, INTO and the locking clauses.
 *
 * Rule: MYSQL-TAIL-FACTS-001. The LIMIT operands and the INTO targets see no
 * column of the query. An INTO variable list whose length differs from the
 * number of columns of a query whose columns are all known is reported. A
 * table named by `FOR UPDATE|SHARE OF` that names no table of the query
 * block is reported; over a set operation the tables are not checked.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select-into.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html
 * ("ER_UNRESOLVED_TABLE_LOCK"). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TailFacts
{
    /**
     * Derives the INTO targets and checks the locking clauses.
     *
     * @param list<LockingClause> $locking
     * @param list<VisibleRelation>|null $visible The tables of the query block, or null over another query
     */
    public function derive(Derivation $derivation, Environment $outer, QueryFact $fact, ?IntoDestination $into, array $locking, ?array $visible): void
    {
        if ($into instanceof IntoVariables) {
            foreach ($into->targets as $target) {
                $derivation->scalar($target, new Environment($derivation->context, $outer));
            }
            if ($fact->shape->complete() && count($fact->shape->slots) !== count($into->targets)) {
                $derivation->report(new CountMismatch(CountedList::IntoVariables, count($fact->shape->slots), count($into->targets)));
            }
        }
        if ($visible === null) {
            return;
        }
        foreach ($locking as $clause) {
            foreach ($clause->tables as $table) {
                $found = false;
                foreach ($visible as $relation) {
                    $found = $found || (new Projection())->admits($derivation, $relation, $table);
                }
                if (!$found) {
                    $derivation->report(new Misuse(MisuseRule::UnknownLockedTable));
                }
            }
        }
    }

    /**
     * Derives the operands of a LIMIT clause, which see no column of their query.
     */
    public function limit(?Limit $limit, Derivation $derivation, Environment $outer): void
    {
        if (!$limit instanceof RowLimit) {
            return;
        }
        $derivation->scalar($limit->count, new Environment($derivation->context, $outer));
        if ($limit->offset !== null) {
            $derivation->scalar($limit->offset, new Environment($derivation->context, $outer));
        }
    }
}
