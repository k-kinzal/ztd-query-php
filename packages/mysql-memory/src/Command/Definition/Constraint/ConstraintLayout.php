<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use Closure;
use MySqlMemory\Dictionary\ForeignKey;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ConstraintName;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey as ForeignKeyElement;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferentialAction;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Writes the CHECK constraints and foreign keys of a stored table as the table elements a change of the table declares again.
 *
 * Each constraint is written with the name it has, so a change keeps the names the server gave;
 * a name the server gave after the old name of a renamed table follows the new name, and a new
 * unnamed constraint is numbered after the highest number the server gave (verified on a live
 * 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility MySqlMemory
 */
final class ConstraintLayout
{
    /**
     * Answers the table elements of the constraints of a table: its foreign keys, then its CHECK constraints.
     *
     * @return list<TableElement>
     */
    public function elements(TableDefinition $definition): array
    {
        $elements = array_map(fn (ForeignKey $key): ForeignKeyElement => $this->foreign($key, $definition), $definition->foreignKeys);
        foreach ($definition->checks as $check) {
            if ($check->node !== null) {
                $elements[] = new CheckConstraint($check->node, $this->named($check->name), $check->enforced ? null : false);
            }
        }

        return $elements;
    }

    /**
     * Answers the table element of a foreign key.
     */
    public function foreign(ForeignKey $key, TableDefinition $definition): ForeignKeyElement
    {
        $columns = array_map(static fn (int $position): ColumnPart => new ColumnPart(new Name($definition->columns[$position]->name)), $key->columns);
        $actions = [];
        if ($key->onDelete !== null) {
            $actions[] = new ReferentialAction(ReferenceEvent::Delete, $key->onDelete);
        }
        if ($key->onUpdate !== null) {
            $actions[] = new ReferentialAction(ReferenceEvent::Update, $key->onUpdate);
        }
        $references = new References(new QualifiedName(new Name($key->parentTable), new Name($key->parentSchema)), array_map(static fn (string $column): Name => new Name($column), $key->parentColumns), null, $actions);

        return new ForeignKeyElement($columns, $references, null, $this->named($key->name));
    }

    /**
     * Answers a constraint name clause.
     */
    public function named(string $name): ConstraintName
    {
        return new ConstraintName(new ColumnName(new Name($name)));
    }

    /**
     * Answers the attributes of a column without its CHECK constraints, which the table elements hold.
     *
     * @param list<ColumnAttribute> $attributes
     * @return list<ColumnAttribute>
     */
    public function unchecked(array $attributes): array
    {
        return array_values(array_filter($attributes, static fn (ColumnAttribute $attribute): bool => !$attribute instanceof CheckConstraint && !$attribute instanceof EnforcementAttribute));
    }

    /**
     * Answers a constraint element renamed for a table renamed: a name the server gave after the old name follows the new one.
     */
    public function renamed(TableElement $element, string $old, string $new): TableElement
    {
        if ($old === $new) {
            return $element;
        }
        if ($element instanceof CheckConstraint && $element->name?->name !== null && Constraints::generated($element->name->name->column->value, $old, '_chk_')) {
            return new CheckConstraint($element->condition, $this->named($new . substr($element->name->name->column->value, strlen($old))), $element->enforced);
        }
        if ($element instanceof ForeignKeyElement) {
            $references = $element->references;
            if ($references->table->name->value === $old) {
                $references = new References(new QualifiedName(new Name($new), $references->table->schema), $references->columns, $references->match, $references->actions);
            }
            $name = $element->constraint?->name?->column->value;
            $renamed = $name !== null && Constraints::generated($name, $old, '_ibfk_') ? $this->named($new . substr($name, strlen($old))) : $element->constraint;

            return new ForeignKeyElement($element->columns, $references, $element->name, $renamed);
        }

        return $element;
    }

    /**
     * Answers a constraint element over the columns of a changed table, or null for a CHECK constraint the change drops with the only column it reads.
     *
     * A foreign key follows its columns to their new names; a CHECK constraint refuses a change
     * that renames a column it reads, or drops one of several columns it reads.
     *
     * @param Closure(string): (string|false|null) $renamed The new name of a column of the table before the change, false when it is dropped, null when the table had no such column
     *
     * @throws SqlError When a column the constraint needs is dropped or renamed
     */
    public function adapted(TableElement $element, Closure $renamed): ?TableElement
    {
        if ($element instanceof ForeignKeyElement) {
            $columns = [];
            foreach ($element->columns as $part) {
                $name = $renamed($part->column->value);
                if ($name === false) {
                    throw ConstraintError::DropForeignColumn->error($part->column->value, $element->constraint?->name?->column->value ?? '');
                }
                $columns[] = new ColumnPart(new Name($name ?? $part->column->value), $part->length, $part->direction);
            }

            return new ForeignKeyElement($columns, $element->references, $element->name, $element->constraint);
        }
        if (!$element instanceof CheckConstraint) {
            return $element;
        }
        $used = array_values(array_unique(array_map(static fn (ColumnUse $use): string => mb_strtolower($use->name->value), (new Walker())->find($element->condition, ColumnUse::class))));
        foreach ($used as $column) {
            $name = $renamed($column);
            if ($name === false && count($used) === 1) {
                return null;
            }
            if ($name === false || ($name !== null && mb_strtolower($name) !== $column)) {
                throw ConstraintError::CheckColumnDependency->error($element->name?->name?->column->value ?? '', $column);
            }
        }

        return $element;
    }

    /**
     * Refuses a change that drops or renames a column a generated column of the changed table reads (ER_DEPENDENT_BY_GENERATED_COLUMN).
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition> $columns The column definitions after the change
     * @param Closure(string): (string|false|null) $renamed The new name of a column of the table before the change, false when it is dropped, null when the table had no such column
     *
     * @throws SqlError When it does
     */
    public function dependencies(array $columns, Closure $renamed): void
    {
        foreach ($columns as $column) {
            $specification = $column->specification;
            if (!$specification instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn) {
                continue;
            }
            foreach ((new Walker())->find($specification->expression, ColumnUse::class) as $use) {
                $name = $renamed($use->name->value);
                if ($name === false || ($name !== null && strcasecmp($name, $use->name->value) !== 0)) {
                    throw ConstraintError::GeneratedDependency->error($use->name->value);
                }
            }
        }
    }

    /**
     * Answers the highest number the server gave a constraint of the elements after a table name and a suffix.
     *
     * @param list<TableElement> $elements
     */
    public function highest(array $elements, string $table, string $suffix): int
    {
        $names = [];
        foreach ($elements as $element) {
            $name = $element instanceof CheckConstraint ? $element->name?->name?->column->value : ($element instanceof ForeignKeyElement ? $element->constraint?->name?->column->value : null);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return Constraints::highest($names, $table, $suffix);
    }
}
