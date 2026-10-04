<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Expression\Limits;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Expression\LiteralDefaults;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnUnique;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\GeneratedStorage;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationObstacle;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationRefused;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DuplicateColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\StrictTypeViolation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownColumn;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Reports what the declared columns of a table rule out in an ALTER TABLE request.
 *
 * Rule: SQLITE-ALTER-PROBLEMS-001. Column names are compared without regard
 * to ASCII case. Nothing is reported about the columns while the column list
 * of the table is not completely known. A table is taken as STRICT when a
 * declared column of it is. The default of an added column is read by
 * SQLITE-LITERAL-DEFAULT-001: a NOT NULL column needs a default that is not
 * NULL, and a default must be one SQLite can compute when the column is
 * added; a default that is not constant at all is reported by the column
 * definition and not again here. Diagnostics: see AlterAddColumn,
 * AlterDropColumn and AlterRenameColumn. Terminates: one pass over the
 * columns.
 * Source: https://sqlite.org/lang_altertable.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class AlterationProblems
{
    /**
     * Counts the known columns of a table that have a name, or answers null when its column list is not completely known.
     */
    public function occurrences(RelationFact $table, Name $column, Derivation $derivation): ?int
    {
        if (!$table->shape->complete() || $table->shape->slots === []) {
            return null;
        }
        $count = 0;
        foreach ($table->shape->slots as $slot) {
            if ($slot->name !== null && $derivation->context->columnNames->equal($slot->name->value, $column->value)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Tells whether the existing table is STRICT, as far as its declared columns show.
     */
    public function strict(RelationFact $table): bool
    {
        foreach ($table->shape->slots as $slot) {
            if ($slot->column?->type instanceof ColumnDomain && $slot->column->type->strict) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the slot a new column adds to the shape of its table.
     */
    public function slot(RelationFact $table, ColumnDefinition $column): OutputSlot
    {
        return new OutputSlot(
            $column->name,
            new Known((new TypeRecording())->domain($column->type, $this->strict($table))),
            $column->notNull() ? Nullability::NotNull : Nullability::Nullable,
        );
    }

    /**
     * Reports what rules out adding a column.
     */
    public function added(RelationFact $table, ColumnDefinition $column, Derivation $derivation): void
    {
        if ($this->occurrences($table, $column->name, $derivation) > 0) {
            $derivation->report(new DuplicateColumn($column->name));
        }
        $domain = (new TypeRecording())->domain($column->type, $this->strict($table));
        if ($domain->strict && !$domain->standard) {
            $derivation->report(new StrictTypeViolation($column->name, $domain->declared));
        }
        $unique = false;
        foreach ($column->constraints as $constraint) {
            $unique = $unique || $constraint instanceof ColumnUnique;
        }
        $defaults = new LiteralDefaults();
        $default = false;
        $literal = true;
        foreach ($defaults->defaults($column->constraints) as $clause) {
            $default = !$defaults->null($clause);
            $literal = !$default || $defaults->literal($clause) || ($clause instanceof DefaultExpression && (new Limits())->nonConstant($clause->expression));
        }
        $plain = $column->generated() === null;
        $obstacles = [
            [$column->primaryKey() !== null, AlterationObstacle::PrimaryKeyColumn],
            [$unique, AlterationObstacle::UniqueColumn],
            [$column->generated()?->storage() === GeneratedStorage::Stored, AlterationObstacle::StoredColumn],
            [$plain && $column->notNull() && !$default, AlterationObstacle::NotNullWithoutDefault],
            [$plain && !$literal, AlterationObstacle::DefaultNotConstant],
        ];
        foreach ($obstacles as [$present, $obstacle]) {
            if ($present) {
                $derivation->report(new AlterationRefused($obstacle));
            }
        }
    }

    /**
     * Reports what rules out dropping a column.
     */
    public function dropped(RelationFact $table, Name $column, Derivation $derivation): void
    {
        $occurrences = $this->occurrences($table, $column, $derivation);
        if ($occurrences === 0) {
            $derivation->report(new UnknownColumn($column));
        } elseif ($occurrences !== null && count($table->shape->slots) === 1) {
            $derivation->report(new AlterationRefused(AlterationObstacle::LastColumn));
        }
    }

    /**
     * Reports what rules out renaming a column.
     */
    public function renamed(RelationFact $table, Name $column, Name $newName, Derivation $derivation): void
    {
        if ($this->occurrences($table, $column, $derivation) === 0) {
            $derivation->report(new UnknownColumn($column));
        }
        if ($this->occurrences($table, $newName, $derivation) > 0 && !$derivation->context->columnNames->equal($column->value, $newName->value)) {
            $derivation->report(new DuplicateColumn($newName));
        }
    }
}
