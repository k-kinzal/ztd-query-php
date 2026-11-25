<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\DeclaredTextCollation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NotNull;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NullAllowed;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ColumnCollation;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Resolves a PostgreSQL declaration's builtin storage and literal default.
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
            [$column->storage, $column->compression, $column->options] === [null, null, []],
            'CREATE TABLE requires an ordinary column definition.'
        );
        $type = $column->type->typeFact($operation->context);
        Check::input($type instanceof Known && !$column->type->setOf, 'The column requires a supported builtin storage kind.');
        $kind = match ($type->descriptor) {
            Builtin::Int2 => IntegerStorage::Small,
            Builtin::Int4 => IntegerStorage::Standard,
            Builtin::Int8 => IntegerStorage::Wide,
            Builtin::Text => CharacterStorage::Text,
            Builtin::Bool => BooleanStorage::Boolean,
            default => throw new InvalidConstruction('The column requires a supported builtin storage kind.'),
        };
        $nullable = null;
        $default = null;
        $collation = null;
        $literals = new LiteralDefaultLowering();
        foreach ($column->qualifiers as $qualifier) {
            if ($qualifier instanceof DefaultExpression && $qualifier->name === null && $default === null) {
                $default = $literals->lower($operation, $qualifier, $kind);
            } elseif (($qualifier instanceof NotNull || $qualifier instanceof NullAllowed) && $qualifier->name === null && $nullable === null) {
                $nullable = $qualifier instanceof NullAllowed;
            } elseif ($qualifier instanceof ColumnCollation && $collation === null) {
                Check::input(
                    DeclaredTextCollation::resolve($qualifier->collation->qualified()) === DeclaredTextCollation::StockC,
                    'The column requires a resolved stock PostgreSQL collation.'
                );
                $collation = TextCollation::C;
            } else {
                throw new InvalidConstruction('The column attribute is outside the native declaration domain.');
            }
        }

        return new TableColumn(new Identifier($column->name->value), $kind, $nullable ?? true, $default, $collation);
    }
}
