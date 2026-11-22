<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Alter;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameTable;

/**
 * Resolves the source of an ordinary table or column rename before publication.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class RenameLowering
{
    /**
     * Exports the absolute identity and canonical declared member name.
     * @throws InvalidConstruction When the table identity or old member is unresolved
     */
    public function lower(BoundStatement $operation, AlterRenameTable|AlterRenameColumn $statement): RenameTable|RenameColumn
    {
        $comparison = Comparison::AsciiInsensitive;
        $path = $operation->context->searchPath;
        Check::input(
            $operation->context->relationNames === $comparison && $operation->context->columnNames === $comparison
                && isset($path[1]) && $comparison->equal($path[0]->value, 'temp') && $comparison->equal($path[1]->value, 'main'),
            'ALTER TABLE requires SQLite identifier comparison and schema search order.',
        );
        $resolution = $operation->facts->relation($statement)->table;
        Check::input(
            $resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable && $resolution->table->complete,
            'ALTER TABLE requires a resolved ordinary table with a complete declaration.',
        );
        $table = $resolution->table;
        $schema = new Identifier(($table->name->schema ?? $operation->context->declarationSchema)->value);
        $name = new Identifier($table->name->name->value);
        $destination = new Identifier($statement->newName->value);

        if ($statement instanceof AlterRenameTable) {
            return new RenameTable($schema, $name, $destination);
        }

        return new RenameColumn($schema, $name, $this->column($table, $statement->column), $destination, $statement->quoteReferences);
    }

    /**
     * Looks up a declared column without applying rowid aliases.
     * @throws InvalidConstruction When the written name does not name one declared column
     */
    public function column(Table $table, Name $name): Identifier
    {
        $match = null;
        foreach ($table->columns as $column) {
            if (Comparison::AsciiInsensitive->equal($column->name->value, $name->value)) {
                Check::input($match === null, 'A column rename requires one declared source column.');
                $match = new Identifier($column->name->value);
            }
        }
        Check::input($match !== null, 'A column rename requires one declared source column.');

        return $match;
    }
}
