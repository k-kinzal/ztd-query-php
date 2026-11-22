<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\GeneratedLowering;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\TypeDescriptor;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Collation as ParsedCollation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnUnique;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ConstraintName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Generated;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NotNull;

/**
 * Resolves the declared type, nullability and value of a declared column.
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
    public function lower(ColumnDefinition $column, BoundStatement $operation, SourceBinding $binding, TypeDescriptor $domain): TableColumn
    {
        $notNull = null;
        $default = null;
        $collation = null;
        $generated = null;
        foreach ($column->constraints as $constraint) {
            if ($constraint instanceof ColumnPrimaryKey || $constraint instanceof ColumnUnique || $constraint instanceof ColumnCheck || $constraint instanceof ConstraintName) {
                continue;
            }
            if ($constraint instanceof ParsedCollation) {
                Check::input($collation === null, 'A checked column can declare one default collation.');
                $collation = Collation::tryFrom(strtr($constraint->name->value, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
                Check::input($collation !== null, 'A checked column needs a builtin collation.');
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

            if ($constraint instanceof Generated) {
                Check::input($generated === null, 'A column can declare only one generation expression.');
                $generated = (new GeneratedLowering())->lower($constraint, $binding);
                continue;
            }

            throw new InvalidConstruction('This column constraint has no complete CREATE TABLE representation.');
        }
        return new TableColumn(
            new Identifier($column->name->value),
            (new DeclaredDatatypeLowering())->lower($column, $domain),
            $notNull,
            $default,
            $collation ?? Collation::Binary,
            $generated,
        );
    }
}
