<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKeyLowering;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
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
        $schemaName = $statement->name->schema->value ?? ($statement->temporary ? 'temp' : 'main');
        $comparison = Comparison::AsciiInsensitive;
        Check::input(!$statement->temporary || $comparison->equal($schemaName, 'temp'), 'A temporary table must be in the temp schema.');
        $columns = [];
        $lowering = new CreateColumnLowering();
        foreach ($statement->columns as $column) {
            $columns[] = $lowering->lower($column, $operation);
        }
        foreach ($operation->context->searchPath as $schema) {
            if ($comparison->equal($schemaName, $schema->value)) {
                return new CreateTable(
                    new Identifier($schema->value),
                    new Identifier($statement->name->name->value),
                    $columns,
                    $statement->ifNotExists,
                    $statement->strict(),
                    $statement->withoutRowid(),
                    (new PrimaryKeyLowering())->lower($statement)
                );
            }
        }

        throw new InvalidConstruction('CREATE TABLE requires a known schema.');
    }
}
