<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View;

use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\BooleanStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CharacterStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence;
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
     * @param list<CreateSequence> $sequences
     * @param list<CreateTable> $tables
     * @param list<CreateView> $views
     */
    public static function check(?Identifier $name, bool $guard, GrammarRelease $release, array $sequences, array $tables, array $views): void
    {
        if ($views === []) {
            return;
        }
        Check::input($name !== null && !$guard, 'Schema views require an unguarded, statically named namespace.');
        $occupied = [];
        foreach ($sequences as $sequence) {
            $occupied[$sequence->name->name->value] = true;
        }
        foreach ($tables as $table) {
            $occupied[$table->target->table->value] ??= $table;
        }
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
                    $fields[$column->name->value] = match ($column->storage) {
                        IntegerStorage::Standard => ColumnKind::Int4,
                        IntegerStorage::Wide => ColumnKind::Int8,
                        CharacterStorage::Text => ColumnKind::Text,
                        BooleanStorage::Boolean => ColumnKind::Boolean,
                        IntegerStorage::Small => null,
                    };
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
    }
}
