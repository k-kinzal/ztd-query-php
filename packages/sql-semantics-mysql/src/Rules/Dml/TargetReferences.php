<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Checks whether DELETE targets can name occurrences in the input relation.
 *
 * An alias hides the table name and cannot be database-qualified. For an
 * unaliased table, an omitted database and case comparison remain session or
 * instance constraints. Only a name with no possible occurrence is rejected.
 * Nested query tables never enter the containing statement's target namespace.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TargetReferences
{
    /**
     * Names every visible occurrence for a deletion without an explicit target subset.
     *
     * @param list<TableSource> $tables The input relation trees
     * @return list<QualifiedName>
     */
    public function names(array $tables): array
    {
        return array_map(static function (TableReference|DerivedTable|JsonTable $table): QualifiedName {
            if ($table instanceof TableReference) {
                return $table->alias === null ? $table->name : new QualifiedName($table->alias);
            }
            return new QualifiedName($table->alias);
        }, $this->occurrences($tables));
    }

    /**
     * Answers the first target absent under every supported name-comparison setting.
     *
     * @param list<QualifiedName> $targets The requested deletion targets
     * @param list<TableSource> $tables The input occurrences
     */
    public function missing(array $targets, array $tables): ?QualifiedName
    {
        $occurrences = $this->occurrences($tables);
        foreach ($targets as $target) {
            $found = false;
            foreach ($occurrences as $table) {
                $found = $found || $this->admits($target, $table);
            }
            if (!$found) {
                return $target;
            }
        }
        return null;
    }

    /**
     * Finds a target that can only name a derived or JSON relation.
     *
     * @param list<QualifiedName> $targets The requested targets
     * @param list<TableSource> $tables The input namespace
     */
    public function readOnly(array $targets, array $tables, ?\SqlSemantics\Platform\MySql\Statement\Query\WithClause $with = null): ?QualifiedName
    {
        $occurrences = $this->occurrences($tables);
        foreach ($targets as $target) {
            $matches = array_filter($occurrences, fn (TableReference|DerivedTable|JsonTable $table): bool => $this->admits($target, $table));
            if ($matches !== [] && array_filter($matches, static fn ($table): bool => $table instanceof TableReference && !(new CommonTargets())->contains($with, $table->name)) === []) {
                return $target;
            }
        }
        return null;
    }

    /**
     * Enumerates only the visible terms, preserving their written order.
     *
     * @param list<TableSource> $tables The input relation tree
     * @return list<TableReference|DerivedTable|JsonTable>
     */
    public function occurrences(array $tables): array
    {
        $pending = array_reverse($tables);
        $result = [];
        while ($pending !== []) {
            $table = array_pop($pending);
            if ($table instanceof TableList) {
                array_push($pending, ...array_reverse($table->members));
            } elseif ($table instanceof JoinedTable) {
                array_push($pending, $table->right, $table->left);
            } elseif ($table instanceof NestedRelation || $table instanceof EscapedRelation || $table instanceof OdbcJoin) {
                $pending[] = $table->relation;
            } elseif ($table instanceof TableReference || $table instanceof DerivedTable || $table instanceof JsonTable) {
                $result[] = $table;
            }
        }
        return $result;
    }

    /**
     * Tests potential identity without resolving a catalog or choosing a current database.
     */
    public function admits(QualifiedName $target, TableReference|DerivedTable|JsonTable $table): bool
    {
        $comparison = Comparison::AsciiInsensitive;
        if ($table->alias !== null) {
            return $target->schema === null && $comparison->equal($target->name->value, $table->alias->value);
        }
        \SqlSemantics\Diagnostic\Check::invariant($table instanceof TableReference, 'A derived or JSON table has an alias.');
        return $comparison->equal($target->name->value, $table->name->name->value)
            && ($target->schema === null || $table->name->schema === null || $comparison->equal($target->schema->value, $table->name->schema->value));
    }
}
