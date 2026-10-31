<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Tail;

use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource;
use SqlSemantics\Statement\Query;

/**
 * Checks the tables a locking clause names against the block it locks, independently of any context.
 *
 * A bare `OF name` names a table of the FROM clause of the block by its correlation
 * name or, when it has none, its table name, byte for byte (`FROM t AS \`T\` FOR UPDATE OF t` is
 * refused, `OF T` is accepted); a name no table carries is refused once the statement is read,
 * before any table is opened (ER_UNRESOLVED_TABLE_LOCK), in every position and in a stored
 * program body. A derived or JSON table named by OF, or a table two OF lists name, is locked
 * twice (ER_DUPLICATE_TABLE_LOCK). A name qualified by a database is resolved with the tables
 * (`FROM t FOR UPDATE OF test.t` opens `test.t`), so it stays with LockedTables, as do the
 * clauses without OF, whose tables depend on which names are common table expressions, and
 * the HIGH_PRIORITY lock of a query's first block. MySQL 5.6 and 5.7 name no tables in a
 * locking clause. Verified on MySQL 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LockedNames
{
    /**
     * Answers the first bare OF name the clauses of a block cannot lock, or null.
     *
     * @param list<LockingClause> $clauses The clauses that lock the block, in the order they apply
     */
    public function problem(TableSource|Dual|ExplicitTable|null $from, array $clauses): ?Misuse
    {
        $terms = match (true) {
            $from instanceof ExplicitTable => [$from],
            $from instanceof TableSource => (new LockedTables())->terms($from),
            default => [],
        };
        $locked = [];
        foreach ($clauses as $clause) {
            foreach ($clause->tables as $table) {
                if ($table->schema !== null) {
                    continue;
                }
                $found = null;
                foreach ($terms as $index => $term) {
                    if ($found === null && $this->names($term, $table->name->value)) {
                        $found = $index;
                    }
                }
                if ($found === null) {
                    return new Misuse(MisuseRule::UnknownLockedTable, $table);
                }
                if (isset($locked[$found]) || $terms[$found] instanceof DerivedTable || $terms[$found] instanceof JsonTable) {
                    return new Misuse(MisuseRule::RepeatedLockedTable, $table);
                }
                $locked[$found] = true;
            }
        }

        return null;
    }

    /**
     * Answers the first bare OF name the clauses written after a query cannot lock in its last block, after the clauses that block writes itself, or null; a block that is neither a query block nor a TABLE statement, such as a VALUES source an INSERT leaves unread, is left alone.
     *
     * @param list<LockingClause> $clauses The clauses written after the query
     */
    public function statement(Query $query, array $clauses): ?Misuse
    {
        [$block, $own] = (new LockedTables())->chain($query);
        if (!$block instanceof Select && !$block instanceof ExplicitTable) {
            return null;
        }

        return $this->problem($block instanceof Select ? $block->from : $block, [...$own, ...$clauses]);
    }

    /**
     * Tells whether a table of the FROM clause carries a name: its correlation name, or its table name when it has none.
     */
    public function names(TableReference|DerivedTable|JsonTable|ExplicitTable $term, string $name): bool
    {
        if ($term instanceof DerivedTable || $term instanceof JsonTable) {
            return $term->alias->value === $name;
        }
        $alias = $term->alias();

        return $alias === null ? $term->name()->name->value === $name : $alias->value === $name;
    }
}
