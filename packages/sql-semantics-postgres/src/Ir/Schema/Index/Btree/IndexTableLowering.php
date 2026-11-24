<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexCreationVisibility;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Binds the indexed table and its physical typed column positions.
 *
 * @visibility SqlSemantics
 */
final class IndexTableLowering
{
    /**
     * Obtains an absolute ordinary table with the supported physical heading.
     *
     * @throws InvalidConstruction When a declared physical type is outside the B-tree profile
     */
    public function lower(BoundStatement $operation, CreateIndex $statement): TableSource
    {
        $resolution = $operation->facts->relation($statement)->table;
        Check::input($resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable
            && $resolution->table->complete, 'A B-tree needs a declared ordinary table with a complete heading.');
        $table = $resolution->table;
        $context = $operation->context;
        $schema = $table->name->schema ?? $context->declarationSchema;
        Check::input($table->name->catalog === null
            && !$context->indexNamespace->partitioned($schema, $table->name->name), 'A B-tree needs a local nonpartitioned table.');
        if ($statement->table->name->schema === null) {
            (new IndexCreationVisibility())->verifyPath($context, $context->indexNamespace->indexes, $table->name->name, $schema);
        }
        $columns = [];
        $types = [];
        foreach ($table->columns as $column) {
            $columns[] = new Identifier($column->name->value);
            $types[] = match ($column->type) {
                Builtin::Int4 => ColumnKind::Int4,
                Builtin::Int8 => ColumnKind::Int8,
                Builtin::Bool => ColumnKind::Boolean,
                default => throw new InvalidConstruction('A B-tree in this profile requires integer or Boolean physical column types.'),
            };
        }

        return new TableSource(new Identifier($schema->value), new Identifier($table->name->name->value), $columns, $statement->table->only, $types);
    }
}
