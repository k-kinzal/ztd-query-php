<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaRelationOrder;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks each namespace index against the actual table and name occupants of preceding members.
 *
 * @visibility SqlSemantics
 */
final class SchemaIndexOrder
{
    /**
     * Checks the ordered index phase of a new schema.
     *
     * @param array<array-key, CreateTable|TableName|true> $occupied
     * @param list<CreateIndex> $indexes
     */
    public static function check(?Identifier $name, bool $guard, GrammarRelease $release, array $occupied, array $indexes): void
    {
        if ($indexes === []) {
            return;
        }
        Check::input($name !== null && !$guard, 'Schema indexes require an unguarded, statically named namespace.');
        foreach ($indexes as $index) {
            Check::input(
                $index->table->schema->value === $name->value && $index->release === $release,
                'A schema index must belong to its namespace and release.'
            );
            Check::input(!$index->concurrently, 'A schema index cannot be built concurrently.');
            $table = $occupied[$index->table->table->value] ?? null;
            Check::input($table instanceof CreateTable, 'A schema index must resolve to a preceding base table.');
            Check::input(count($index->table->columns) === count($table->columns), 'A schema index needs the full ordered physical heading.');
            foreach ($table->columns as $slot => $column) {
                Check::input(
                    $column->name->value === $index->table->columns[$slot]->value
                    && SchemaRelationOrder::columnKind($column) === $index->table->columnKind($slot),
                    'A schema index needs the full ordered physical heading.'
                );
            }
            $coordinate = $index->name->value;
            $presence = isset($occupied[$coordinate]) ? IndexPresence::Occupied : IndexPresence::Vacant;
            Check::input($presence === $index->presence, 'A schema index must retain the actual presence of its name.');
            $occupied[$coordinate] ??= true;
        }
    }
}
