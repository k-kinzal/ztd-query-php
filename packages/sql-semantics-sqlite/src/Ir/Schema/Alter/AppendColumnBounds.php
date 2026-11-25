<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Alter;

use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckConstraint;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks the shared table and heading conditions of an appended column.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class AppendColumnBounds
{
    /**
     * Checks the fixed limits and does not resolve or widen the supplied heading.
     *
     * @param non-empty-list<Identifier> $heading The complete declared heading of the identified table
     * @param list<CheckConstraint> $checks The inline conditions for the appended column
     * @return list<ColumnReference> The checked inline condition references
     */
    public function check(Identifier $schema, Identifier $table, array $heading, bool $strict, TableColumn $column, array $checks): array
    {
        Check::input($schema->value !== '', 'An ALTER TABLE target needs a nonempty schema identifier.');
        Check::input(!str_starts_with($table->key(), 'sqlite_'), 'A column addition cannot target the reserved SQLite namespace.');
        Check::input(count($heading) < 2000, 'A column addition cannot exceed 2,000 declared columns.');
        $keys = [];
        foreach ($heading as $identifier) {
            $key = $identifier->key();
            Check::input(!isset($keys[$key]), 'The prior heading must have distinct declared names.');
            $keys[$key] = true;
        }
        Check::input(!isset($keys[$column->name->key()]), 'The added column must have a distinct declared name.');
        Check::input(!$strict || $column->type !== DeclaredDatatypeName::Untyped, 'A STRICT column addition requires a standard type.');
        $owner = count($heading);
        $scope = [...$heading, $column->name];
        $expressions = [];
        $named = false;
        foreach ($checks as $check) {
            Check::input($check->column === $owner, 'An appended CHECK must belong to the new column slot.');
            $check->checkPresentation($scope, $named);
            $named = $check->label instanceof Identifier;
            $expressions[] = [$check->condition, 0, 1];
        }
        return (new ExpressionScope())->check([$owner + 1], $expressions, false);
    }

    /**
     * Copies the exact names selected by the checked references in ascending slot order.
     *
     * @param non-empty-list<Identifier> $scope The checked table heading including the new field
     * @param list<ColumnReference> $references The checked operand occurrences
     * @return array<int, Identifier>
     */
    public function bindings(array $scope, array $references): array
    {
        $bindings = [];
        foreach ($references as $reference) {
            $bindings[$reference->slot] = $scope[$reference->slot];
        }
        ksort($bindings);

        return $bindings;
    }
}
