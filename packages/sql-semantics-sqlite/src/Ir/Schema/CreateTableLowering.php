<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\TableKeyLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckLowering;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOptionKind;

/**
 * Converts a column-based table declaration to a complete value.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CreateTableLowering
{
    /**
     * Resolves the location and records the native column declarations.
     * @throws InvalidConstruction When the statement uses an unknown schema
     */
    public function lower(BoundStatement $operation, ParsedCreateTable $statement): CreateTable
    {
        foreach ($statement->options as $option) {
            Check::input($option->kind() === TableOptionKind::Strict || $option->kind() === TableOptionKind::WithoutRowid, 'This table option has no complete CREATE TABLE representation.');
        }
        $schema = (new CreateSchema())->resolve($operation, $statement->name, $statement->temporary);
        $names = [];
        foreach ($statement->columns as $column) {
            $names[] = new Identifier($column->name->value);
        }
        $binding = new SourceBinding($operation, $statement, $names);
        $table = $operation->facts->declarations[0] ?? null;
        Check::invariant($table !== null && $operation->statement === $statement, 'The binding describes the table being lowered.');
        $columns = [];
        $lowering = new CreateColumnLowering();
        foreach ($statement->columns as $slot => $column) {
            $declared = $table->columns[$slot] ?? null;
            Check::invariant($declared !== null && $declared->name === $column->name, 'The binding describes the same ordered column declaration.');
            $columns[] = $lowering->lower($column, $operation, $binding, $declared->type);
        }
        return new CreateTable(
            $schema,
            new Identifier($statement->name->name->value),
            $columns,
            $statement->ifNotExists,
            $statement->strict(),
            $statement->withoutRowid(),
            (new TableKeyLowering())->lower($statement, $columns),
            (new CheckLowering())->lower($operation, $statement, $columns),
        );
    }
}
