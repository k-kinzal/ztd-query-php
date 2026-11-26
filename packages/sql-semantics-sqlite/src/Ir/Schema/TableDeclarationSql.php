<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\TableKeySql;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\UniqueKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckConstraint;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\DefinitionSource;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ImplicitCheckLabel;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Composes a table definition from its columns and constraints.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableDeclarationSql
{
    /**
     * Emits an appended column and its checked conditions in their declaration order.
     *
     * @param array<int, Identifier> $bindings The exact names referenced by the appended definition
     * @param list<CheckConstraint> $conditions The conditions owned by the appended slot
     */
    public function addition(TableColumn $column, array $bindings, array $conditions): string
    {
        $source = new DefinitionSource($bindings);
        $expressions = new SelectSql();
        $definition = $this->column($column, $expressions, $source);
        foreach ($conditions as $check) {
            $definition .= ' ' . $this->checkClause($check, $expressions, $source);
        }

        return $definition;
    }

    /**
     * Emits inline constraints on their declared column and table constraints after the columns.
     * @param non-empty-list<TableColumn> $columns
     * @param list<PrimaryKey|UniqueKey> $keys
     * @param list<CheckConstraint> $checks
     * @return list<string>
     */
    public function declarations(array $columns, array $keys, array $checks): array
    {
        $inline = [];
        $table = [];
        $keySql = new TableKeySql();
        foreach ($keys as $key) {
            if ($key->inline) {
                $slot = $key->terms[0]->slot;
                $inline[$slot] = ($inline[$slot] ?? '') . $keySql->inline($key);
            } else {
                $table[] = $keySql->table($key, $columns);
            }
        }
        $names = [];
        foreach ($columns as $column) {
            $names[] = $column->name;
        }
        $source = new DefinitionSource($names);
        $expressions = new SelectSql();
        foreach ($checks as $check) {
            $sql = $this->checkClause($check, $expressions, $source);
            if ($check->column === null) {
                $table[] = $sql;
            } else {
                $inline[$check->column] = ($inline[$check->column] ?? '') . ' ' . $sql;
            }
        }

        $parts = [];
        foreach ($columns as $slot => $column) {
            $parts[] = $this->column($column, $expressions, $source) . ($inline[$slot] ?? '');
        }

        return [...$parts, ...$table];
    }

    /**
     * Writes the checked constraint in the selected naming role.
     */
    public function checkClause(CheckConstraint $check, SelectSql $expressions, DefinitionSource $source): string
    {
        $condition = $check->condition;
        if ($check->label instanceof ImplicitCheckLabel) {
            Check::invariant($condition instanceof Comparison && $condition->right instanceof IntegerLiteral, 'The implicit CHECK has an integer operand.');

            return 'CHECK (' . $check->label->expression($condition->right) . ')';
        }

        return 'CONSTRAINT ' . $check->label->quoted() . ' CHECK (' . $expressions->expression($condition, $source) . ')';
    }

    /**
     * Emits the column declaration, keeping the untyped case distinct.
     */
    public function column(TableColumn $column, SelectSql $expressions, DefinitionSource $source): string
    {
        $type = $column->type === DeclaredDatatypeName::Untyped ? '' : ' ' . $column->type->value;

        $operand = $column->default;
        $default = '';
        if ($operand instanceof ColumnDefaultExpression) {
            $default = ' DEFAULT (' . $expressions->expression($operand->expression, null) . ')';
        } elseif ($operand !== null) {
            $default = ' DEFAULT ' . $expressions->expression($operand, null);
        }

        $constraint = match ($column->notNull) {
            null => '',
            NotNullConflict::Abort => ' NOT NULL',
            NotNullConflict::Fail => ' NOT NULL ON CONFLICT FAIL',
            NotNullConflict::Ignore => ' NOT NULL ON CONFLICT IGNORE',
            NotNullConflict::Replace => ' NOT NULL ON CONFLICT REPLACE',
            NotNullConflict::Rollback => ' NOT NULL ON CONFLICT ROLLBACK',
        };

        $collation = $column->collation === Collation::Binary ? '' : ' COLLATE ' . $column->collation->value;

        $generated = $column->generated;
        $generation = $generated === null ? '' : ' GENERATED ALWAYS AS ('
            . $expressions->expression($generated->expression, $source) . ') ' . $generated->mode->value;

        return $column->name->quoted() . $type . $constraint . $default . $collation . $generation;
    }
}
