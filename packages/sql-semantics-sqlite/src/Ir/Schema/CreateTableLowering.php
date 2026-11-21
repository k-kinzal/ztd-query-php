<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NotNull;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable as ParsedCreateTable;

/**
 * Converts an ordinary column-based table declaration to a complete value.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CreateTableLowering
{
    /**
     * Resolves the location and records the native column declarations.
     * @throws InvalidConstruction When the statement uses an unknown schema
     */
    public function lower(Operation $operation, ParsedCreateTable $statement): CreateTable
    {
        Check::input($statement->constraints === [] && $statement->options === [], 'Complete SQLite CREATE TABLE currently requires an ordinary rowid table without table constraints.');
        $schemaName = $statement->name->schema->value ?? ($statement->temporary ? 'temp' : 'main');
        $comparison = Comparison::AsciiInsensitive;
        Check::input(!$statement->temporary || $comparison->equal($schemaName, 'temp'), 'A temporary table must be in the temp schema.');
        $columns = [];
        foreach ($statement->columns as $column) {
            $constraint = $column->constraints[0] ?? null;
            Check::input(
                $column->constraints === [] || (count($column->constraints) === 1 && $constraint instanceof NotNull && $constraint->conflict === null),
                'This column constraint has no complete CREATE TABLE representation.',
            );
            $type = DeclaredDatatypeName::Untyped;
            if ($column->type !== null) {
                Check::input(count($column->type->words) === 1 && $column->type->arguments === [], 'The declared type must be one supported type name.');
                $type = DeclaredDatatypeName::tryFrom(strtoupper($column->type->text()));
                Check::input($type !== null && $type !== DeclaredDatatypeName::Untyped, 'This declared type has no complete CREATE TABLE representation.');
            }
            $columns[] = new TableColumn(new Identifier($column->name->value), $type, $constraint !== null);
        }
        foreach ($operation->context->searchPath as $schema) {
            if ($comparison->equal($schemaName, $schema->value)) {
                return new CreateTable(new Identifier($schema->value), new Identifier($statement->name->name->value), $columns, $statement->ifNotExists);
            }
        }

        throw new InvalidConstruction('CREATE TABLE requires a known schema.');
    }
}
