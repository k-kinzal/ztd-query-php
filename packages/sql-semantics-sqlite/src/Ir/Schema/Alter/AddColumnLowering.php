<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Alter;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateColumnLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckLowering;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterAddColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Collation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ConstraintName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Generated;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NotNull;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;

/**
 * Publishes an appended column from resolved table and column facts.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class AddColumnLowering
{
    /**
     * Exports the checked identity, declared heading, strictness and appended column.
     * @throws InvalidConstruction When the column uses an unrepresented constraint
     */
    public function lower(BoundStatement $operation, AlterAddColumn $statement): AddColumn|AddGeneratedColumn
    {
        [$schema, $name, $table] = (new AlterTargetLowering())->resolve($operation, $statement);
        $constraints = $statement->column->constraints;
        foreach ($constraints as $position => $constraint) {
            if ($constraint instanceof ConstraintName) {
                Check::input(($constraints[$position + 1] ?? null) instanceof ColumnCheck, 'A column addition supports constraint names on inline CHECK conditions.');
                continue;
            }
            Check::input(
                $constraint instanceof Collation || $constraint instanceof DefaultExpression
                    || $constraint instanceof DefaultLiteral || $constraint instanceof DefaultWord || $constraint instanceof NotNull
                    || $constraint instanceof Generated || $constraint instanceof ColumnCheck,
                'This constraint has no complete column addition representation.',
            );
        }

        $type = $operation->facts->relation($statement)->shape->slots[count($table->columns)]->type ?? null;
        Check::invariant($type instanceof Known && $type->descriptor instanceof ColumnDomain, 'The new member has its resolved SQLite declared domain.');
        $strict = $type->descriptor->strict;
        $heading = [];
        foreach ($table->columns as $column) {
            Check::input($column->type instanceof ColumnDomain && $column->type->strict === $strict, 'A column addition requires a resolved and consistent table type mode.');
            $heading[] = new Identifier($column->name->value);
        }
        $binding = new SourceBinding($operation, $statement, [...$heading, new Identifier($statement->column->name->value)]);
        $column = (new CreateColumnLowering())->lower($statement->column, $operation, $binding, $type->descriptor);
        $checks = (new CheckLowering())->collect($binding, $constraints, count($heading));

        return $column->generated === null
            ? new AddColumn($schema, $name, $heading, $strict, $column, $checks)
            : new AddGeneratedColumn($schema, $name, $heading, $strict, $column, $checks);
    }
}
