<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaRelationOrder;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks a namespace's persistent views against actual preceding relation members.
 * @visibility SqlSemantics
 */
final class SchemaViewOrder
{
    /**
     * Checks unique view targets and each exact local base-table operand.
     * @param array<array-key, CreateTable|true> $occupied Checked earlier occupants
     * @param list<CreateView> $views
     * @return array<array-key, CreateTable|true> Occupants after the view phase
     */
    public static function check(?Identifier $name, bool $guard, GrammarRelease $release, array $occupied, array $views): array
    {
        if ($views === []) {
            return $occupied;
        }
        Check::input($name !== null && !$guard, 'Schema views require an unguarded, statically named namespace.');
        foreach ($views as $view) {
            Check::input(
                $view->name->schema->value === $name->value && $view->query->release === $release,
                'A schema view must belong to its namespace and release.'
            );
            $coordinate = $view->name->view->value;
            Check::input(!isset($occupied[$coordinate]), 'A schema view requires an unused relation name.');
            $source = $view->query instanceof Select ? $view->query->source : null;
            if ($source instanceof TableSource && $source->schema->value === $name->value) {
                $local = $occupied[$source->table->value] ?? null;
                Check::input($local instanceof CreateTable, 'A schema view must resolve its local input to a preceding base table.');
                $fields = [];
                foreach ($local->columns as $column) {
                    $fields[$column->name->value] = SchemaRelationOrder::columnKind($column);
                }
                foreach ($source->columns as $slot => $column) {
                    Check::input(
                        isset($fields[$column->value]) && $fields[$column->value] === $source->columnKind($slot),
                        'A schema view requires each local input field in the checked native kind.'
                    );
                }
            }
            $occupied[$coordinate] = true;
        }

        return $occupied;
    }
}
