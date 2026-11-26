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
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterAddColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterDropColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameTable;

/**
 * Selects an ordinary table under SQLite name comparison and schema search order.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class AlterTargetLowering
{
    /**
     * Answers the absolute identity and the resolved declaration used during lowering.
     *
     * @return array{Identifier, Identifier, Table}
     * @throws InvalidConstruction When the ordinary table is unresolved
     */
    public function resolve(BoundStatement $operation, AlterAddColumn|AlterDropColumn|AlterRenameTable|AlterRenameColumn $statement): array
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

        return [
            new Identifier(($table->name->schema ?? $operation->context->declarationSchema)->value),
            new Identifier($table->name->name->value),
            $table,
        ];
    }
}
