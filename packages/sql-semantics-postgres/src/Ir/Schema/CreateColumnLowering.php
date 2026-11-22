<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NotNull;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NullAllowed;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Resolves a PostgreSQL declaration's builtin signed column and literal default.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CreateColumnLowering
{
    /**
     * Reads the storage, nullability and default roles before publishing a column.
     *
     * @throws InvalidConstruction When the definition has unsupported attributes or values
     */
    public function lower(BoundStatement $operation, ColumnDefinition $column): TableColumn
    {
        Check::input(
            $column->storage === null && $column->compression === null && $column->options === [],
            'CREATE TABLE requires an ordinary column definition.'
        );
        $type = $column->type->typeFact($operation->context);
        Check::input($type instanceof Known && !$column->type->setOf, 'The column requires a builtin signed integer storage role.');
        $kind = match ($type->descriptor) {
            Builtin::Int2 => IntegerStorage::Small,
            Builtin::Int4 => IntegerStorage::Standard,
            Builtin::Int8 => IntegerStorage::Wide,
            default => throw new InvalidConstruction('The column requires a builtin signed integer storage role.'),
        };
        $nullable = null;
        $default = null;
        $path = $operation->context->searchPath;
        $catalogFirst = ($path[0]->value === 'pg_temp' ? ($path[1]->value ?? null) : $path[0]->value) === 'pg_catalog';
        foreach ($column->qualifiers as $qualifier) {
            if ($qualifier instanceof DefaultExpression && $qualifier->name === null && $default === null) {
                $default = (new ExpressionLowering())->lower($qualifier->value, new SourceBinding($operation, null), $catalogFirst);
                Check::input($default instanceof IntegerLiteral || $default instanceof NullLiteral, 'The column requires an integer or NULL default.');
            } elseif (($qualifier instanceof NotNull || $qualifier instanceof NullAllowed) && $qualifier->name === null && $nullable === null) {
                $nullable = $qualifier instanceof NullAllowed;
            } else {
                throw new InvalidConstruction('The column attribute is outside the native declaration domain.');
            }
        }

        return new TableColumn(new Identifier($column->name->value), $kind, $nullable ?? true, $default);
    }
}
