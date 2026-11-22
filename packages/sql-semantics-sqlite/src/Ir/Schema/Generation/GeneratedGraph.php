<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Generation;

use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks the dependency order of generated columns in the complete declaration.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class GeneratedGraph
{
    /**
     * Rejects cycles and enforces table and key roles for generated slots.
     *
     * @param non-empty-list<TableColumn> $columns
     */
    public function check(array $columns, ?PrimaryKey $primaryKey): void
    {
        $scope = new ExpressionScope();
        $widths = [count($columns)];
        $dependents = [];
        $remaining = [];
        $ready = [];
        foreach ($columns as $slot => $column) {
            if ($column->generated === null) {
                continue;
            }
            $inputs = [];
            foreach ($scope->check($widths, [[$column->generated->expression, 0, 1]], false) as $reference) {
                if ($columns[$reference->slot]->generated !== null) {
                    $inputs[$reference->slot] = $reference->slot;
                }
            }
            foreach ($inputs as $input) {
                $dependents[$input][] = $slot;
            }
            $remaining[$slot] = count($inputs);
            if ($inputs === []) {
                $ready[] = $slot;
            }
        }
        Check::input(count($remaining) < count($columns), 'A generated table needs at least one ordinary column.');
        foreach ($primaryKey->terms ?? [] as $term) {
            Check::input(!isset($remaining[$term->slot]), 'A generated column cannot belong to the primary key.');
        }

        $ordered = 0;
        while ($ready !== []) {
            $input = array_pop($ready);
            ++$ordered;
            foreach ($dependents[$input] ?? [] as $slot) {
                if (--$remaining[$slot] === 0) {
                    $ready[] = $slot;
                }
            }
        }
        Check::input($ordered === count($remaining), 'The generated column dependency graph cannot have a cycle.');
    }
}
