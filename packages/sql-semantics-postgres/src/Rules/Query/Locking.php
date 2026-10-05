<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockingClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\FunctionTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Join;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\ParenthesizedJoin;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\RelationList;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlTable;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Relation;

/**
 * Checks which FROM items a locking clause can lock.
 *
 * Rule: PG-LOCKING-TARGET-001. Scope: the locking clauses of a selection.
 * A clause with OF locks the named FROM items; a named join, function,
 * table function or WITH query cannot be locked ("FOR UPDATE cannot be
 * applied to a join" and the like). A clause without OF locks every table
 * of the FROM clause and skips the items that cannot be locked. A table on
 * the nullable side of an outer join (the right side of LEFT, the left side
 * of RIGHT, both sides of FULL) cannot be locked. Reference to a WITH query
 * is told from a table by resolving its name where the selection stands.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FOR-UPDATE-SHARE,
 * `transformLockingClause` in `src/backend/parser/analyze.c` and
 * `make_outerjoininfo` in `src/backend/optimizer/plan/initsplan.c` of PostgreSQL 17.
 * Termination: one walk over the FROM tree per clause. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Locking
{
    /**
     * Reports the FROM items the locking clauses name or cover that cannot be locked.
     *
     * @param list<LockingClause> $clauses
     * @param list<VisibleRelation> $visible The relations of the FROM items of the query
     * @param Relation|null $from The FROM clause of the query
     * @param Environment $base The environment the query stands in, which holds its WITH queries
     */
    public function check(array $clauses, array $visible, ?Relation $from, Derivation $derivation, Environment $base): void
    {
        $tables = $from === null ? [] : $this->tables($from);
        foreach ($clauses as $clause) {
            $subject = new Name('FOR ' . $clause->strength->value);
            $targets = [];
            foreach ($clause->relations as $name) {
                $item = $name->schema === null ? $this->named($visible, $name->name, $derivation) : null;
                $rule = $item === null ? null : $this->refused($item, $derivation, $base);
                if ($rule !== null) {
                    $derivation->report(new QueryMisuse($rule, $subject));
                } elseif ($item instanceof TableInput) {
                    $targets[] = $item;
                }
            }
            foreach ($clause->relations === [] ? array_column($tables, 0) : $targets as $target) {
                if (in_array([$target, true], $tables, true) && $this->refused($target, $derivation, $base) === null) {
                    $derivation->report(new QueryMisuse(QueryMisuseRule::LockingNullableSide, $subject));
                    break;
                }
            }
        }
    }

    /**
     * Answers the FROM item an unqualified name of a locking clause denotes, or null when no item has the name.
     *
     * @param list<VisibleRelation> $visible
     */
    public function named(array $visible, Name $name, Derivation $derivation): ?Relation
    {
        $scope = new Environment($derivation->context);
        foreach ($visible as $relation) {
            if ((new Visibility())->admits($scope, $relation, new QualifiedName($name))) {
                return $relation->relation;
            }
        }

        return null;
    }

    /**
     * Answers the rule a FROM item breaks when it is named in a locking clause, or null when it can be locked.
     */
    public function refused(Relation $item, Derivation $derivation, Environment $base): ?QueryMisuseRule
    {
        return match (true) {
            $item instanceof ParenthesizedJoin, $item instanceof Join => QueryMisuseRule::LockingOnJoin,
            $item instanceof FunctionTable => QueryMisuseRule::LockingOnFunction,
            $item instanceof XmlTable, $item instanceof JsonTable => QueryMisuseRule::LockingOnTableFunction,
            $item instanceof TableInput && $derivation->table($item->name(), $base) instanceof CommonTable => QueryMisuseRule::LockingOnCommonTable,
            default => null,
        };
    }

    /**
     * Answers the table references of a FROM clause, each with whether it is on the nullable side of an outer join; `$nullable` tells whether the clause itself is.
     *
     * @return list<array{TableInput, bool}>
     */
    public function tables(Relation $from, bool $nullable = false): array
    {
        return match (true) {
            $from instanceof TableInput => [[$from, $nullable]],
            $from instanceof ParenthesizedJoin => $this->tables($from->join, $nullable),
            $from instanceof RelationList => array_merge([], ...array_map(fn (Relation $member): array => $this->tables($member, $nullable), $from->items)),
            $from instanceof Join => [
                ...$this->tables($from->left, $nullable || $from->kind === JoinKind::Right || $from->kind === JoinKind::Full),
                ...$this->tables($from->right, $nullable || $from->kind === JoinKind::Left || $from->kind === JoinKind::Full),
            ],
            default => [],
        };
    }
}
