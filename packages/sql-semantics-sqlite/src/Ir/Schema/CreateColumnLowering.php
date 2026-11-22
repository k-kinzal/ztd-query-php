<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NotNull;

/**
 * Resolves the declared type, nullability and default of an ordinary column.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CreateColumnLowering
{
    /**
     * Constructs a checked column from the admitted constraint roles.
     *
     * @throws InvalidConstruction When the column has an unsupported declaration
     */
    public function lower(ColumnDefinition $column, BoundStatement $operation): TableColumn
    {
        $notNull = null;
        $default = null;
        foreach ($column->constraints as $constraint) {
            if ($constraint instanceof ColumnPrimaryKey) {
                continue;
            }
            if ($constraint instanceof NotNull) {
                Check::input($notNull === null, 'A checked CREATE TABLE column can declare one NOT NULL constraint.');
                $notNull = $constraint->conflict === null ? NotNullConflict::Abort : NotNullConflict::from($constraint->conflict->value);
                continue;
            }
            if ($constraint instanceof DefaultLiteral || $constraint instanceof DefaultExpression || $constraint instanceof DefaultWord) {
                Check::input($default === null, 'A checked CREATE TABLE column can declare one default.');
                $default = (new DefaultLowering())->lower($constraint, $operation);
                continue;
            }

            throw new InvalidConstruction('This column constraint has no complete CREATE TABLE representation.');
        }
        $type = DeclaredDatatypeName::Untyped;
        if ($column->type !== null) {
            Check::input(count($column->type->words) === 1 && $column->type->arguments === [], 'The declared type must be one supported type name.');
            $spelling = strtr($column->type->text(), 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
            $type = DeclaredDatatypeName::tryFrom($spelling);
            Check::input($type !== null && $type !== DeclaredDatatypeName::Untyped, 'This declared type has no complete CREATE TABLE representation.');
        }

        return new TableColumn(new Identifier($column->name->value), $type, $notNull, $default);
    }
}
