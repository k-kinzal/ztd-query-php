<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Applies the locking clauses of a query to the tables of the query block they lock and reports the clauses the server refuses.
 *
 * Rule: MYSQL-LOCKED-TABLES-001. The locking clauses written after a query
 * lock the tables of its last query block: the block itself, the block in
 * parentheses, the last operand of a set operation; the tables of a TABLE
 * statement are its table, and VALUES has none. The clauses apply in written
 * order, those written inside parentheses before those outside. A clause
 * without OF locks the tables of the FROM clause of the block in written
 * order, leaving out derived tables and common table references; a clause
 * with OF locks each table it names, found by its correlation name or, when
 * it has none, its table name, whatever its kind. A table a clause names
 * that the block does not hold is reported (ER_UNRESOLVED_TABLE_LOCK), and
 * so is a table a clause locks that is locked already
 * (ER_DUPLICATE_TABLE_LOCK): by an earlier clause, as a derived table or a
 * JSON table, which count as locked from the start, or, when the first
 * query block of the query holds HIGH_PRIORITY, by that option, which
 * locks every table. A clause without OF names the table by its
 * correlation name or table name; a clause with OF names it as written.
 * The first problem ends the check. Terminates: the walk follows strictly
 * smaller parts of the query and of the FROM clause. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/select.html ("Locking Read
 * Concurrency with NOWAIT and SKIP LOCKED", "OF tbl_name"),
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html.
 * Status: Partial: HIGH_PRIORITY counts for the clauses of a query whose
 * own first block holds it, not for those of its subqueries, derived
 * tables, common tables and later set operands.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LockedTables
{
    /**
     * Applies the clauses of a query block or query statement after those written inside it, and reports the first problem.
     */
    public function check(Derivation $derivation, Environment $outer, Select|QueryStatement $query): void
    {
        if ($query->locking === []) {
            return;
        }
        [$block, $clauses, $common] = $this->chain($query);
        $entries = $this->entries($derivation, $outer, $block, $common, $this->leading($query)->options ?? []);
        $earlier = array_slice($clauses, 0, count($clauses) - count($query->locking));
        foreach ($earlier as $clause) {
            $entries = $this->apply($derivation, $clause, $entries)[0];
        }
        foreach ($query->locking as $clause) {
            [$entries, $problem] = $this->apply($derivation, $clause, $entries);
            if ($problem !== null) {
                $derivation->report($problem);

                return;
            }
        }
    }

    /**
     * Applies one clause to the tables of the block and answers their new state with the first problem of the clause.
     *
     * @param list<array{VisibleRelation, bool, bool}> $entries Each table with whether a clause without OF locks it and whether it is locked
     * @return array{list<array{VisibleRelation, bool, bool}>, Misuse|null}
     */
    public function apply(Derivation $derivation, LockingClause $clause, array $entries): array
    {
        if ($clause->tables === []) {
            foreach ($entries as $index => [$relation, $listed, $locked]) {
                if (!$listed) {
                    continue;
                }
                if ($locked) {
                    return [$entries, new Misuse(MisuseRule::RepeatedLockedTable, $relation->alias ?? $relation->name?->name)];
                }
                $entries[$index][2] = true;
            }

            return [$entries, null];
        }
        foreach ($clause->tables as $table) {
            $found = null;
            foreach ($entries as $index => [$relation]) {
                if ($found === null && (new Projection())->admits($derivation, $relation, $table)) {
                    $found = $index;
                }
            }
            if ($found === null) {
                return [$entries, new Misuse(MisuseRule::UnknownLockedTable, $table)];
            }
            if ($entries[$found][2]) {
                return [$entries, new Misuse(MisuseRule::RepeatedLockedTable, $table)];
            }
            $entries[$found][2] = true;
        }

        return [$entries, null];
    }

    /**
     * Answers the block a query locks, the clauses that lock it in the order they apply and the common tables the query defines on the way.
     *
     * @return array{Query|null, list<LockingClause>, list<Name>}
     */
    public function chain(Query $query): array
    {
        if ($query instanceof Select) {
            return [$query, $query->locking, []];
        }
        if ($query instanceof QueryStatement) {
            [$block, $clauses, $common] = $this->chain($query->query);

            return [$block, [...$clauses, ...$query->locking], $common];
        }
        if ($query instanceof QueryExpression) {
            [$block, $clauses, $common] = $this->chain($query->body);
            $names = $query->with instanceof With ? array_map(static fn ($table): Name => $table->name, $query->with->tables) : [];

            return [$block, $clauses, [...$common, ...$names]];
        }

        return match (true) {
            $query instanceof ParenthesizedQuery => $this->chain($query->query),
            $query instanceof SetOperation, $query instanceof OrderedSetOperation => $this->chain($query->right),
            default => [$query, [], []],
        };
    }

    /**
     * Answers the first query block of a query, whose options hold HIGH_PRIORITY when it is given.
     */
    public function leading(Query|LeadingUnion $query): ?Select
    {
        return match (true) {
            $query instanceof Select => $query,
            $query instanceof QueryStatement, $query instanceof ParenthesizedQuery => $this->leading($query->query),
            $query instanceof QueryExpression => $this->leading($query->body),
            $query instanceof SetOperation, $query instanceof OrderedSetOperation, $query instanceof LeadingUnion => $this->leading($query->left),
            default => null,
        };
    }

    /**
     * Answers the tables of a block in written order, each with whether a clause without OF locks it and whether it starts locked.
     *
     * @param list<Name> $common The common tables the query defines around the block
     * @param list<SelectOption> $options The options of the first block of the query
     * @return list<array{VisibleRelation, bool, bool}>
     */
    public function entries(Derivation $derivation, Environment $outer, ?Query $block, array $common, array $options): array
    {
        $priority = in_array(SelectOption::HighPriority, $options, true);
        $terms = match (true) {
            $block instanceof Select && $block->from !== null => $this->terms($block->from),
            $block instanceof ExplicitTable => [$block],
            default => [],
        };
        $entries = [];
        foreach ($terms as $term) {
            if ($term instanceof DerivedTable || $term instanceof JsonTable) {
                $entries[] = [new VisibleRelation($term, new RowShape([]), $term->alias), $term instanceof JsonTable, true];
                continue;
            }
            $name = $term->name();
            $shared = $name->schema === null && ($outer->commonTable($name->name) !== null || $this->defines($derivation, $common, $name->name));
            $entries[] = [new VisibleRelation($term, new RowShape([]), $term->alias(), $name), !$shared, $priority];
        }

        return $entries;
    }

    /**
     * Tells whether a name names one of the common tables the query defines around the block.
     *
     * @param list<Name> $common
     */
    public function defines(Derivation $derivation, array $common, Name $name): bool
    {
        foreach ($common as $defined) {
            if ($derivation->context->relationNames->equal($defined->value, $name->value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the tables, derived tables and JSON tables of a FROM clause in written order.
     *
     * @return list<TableReference|DerivedTable|JsonTable>
     */
    public function terms(Relation $relation): array
    {
        return match (true) {
            $relation instanceof TableReference, $relation instanceof DerivedTable, $relation instanceof JsonTable => [$relation],
            $relation instanceof TableList => array_merge([], ...array_map($this->terms(...), $relation->members)),
            $relation instanceof JoinedTable => [...$this->terms($relation->left), ...$this->terms($relation->right)],
            $relation instanceof NestedRelation, $relation instanceof OdbcJoin, $relation instanceof EscapedRelation => $this->terms($relation->relation),
            default => [],
        };
    }
}
