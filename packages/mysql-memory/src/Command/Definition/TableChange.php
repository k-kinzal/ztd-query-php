<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\DefaultSetting;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConstraintEnforcement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\IndexVisibility;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameTo;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\Reorder;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\SetTableOptions;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ToggleKeys;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlterModifier;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\PartitionBy;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\SecondaryLoad;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\StandaloneCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\TablespaceCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\TrailingCommand;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\CollateAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\NumberOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Applies the actions of ALTER TABLE to the layout of a table, as the server applies them.
 *
 * The actions name the columns and indexes the table had before the statement: a column is
 * dropped, changed, renamed or given a default by its old name, and an index is dropped or
 * renamed by its old name, so ADD COLUMN b, DROP COLUMN b drops the old b and adds a new one. A
 * changed column without a position keeps its place. The new columns, and the changed columns
 * with a position, are then placed in written order: at the end, first, or after a column of the
 * new table. A column named twice, and a table without columns, are refused last. The new
 * indexes and constraints follow the ones the table keeps, so an action cannot drop or rename an
 * index the statement adds. ALGORITHM and LOCK are remembered for the server's
 * checks. Every rule was verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility MySqlMemory
 */
final class TableChange
{
    /**
     * The ALGORITHM written last, in lowercase, or null when none or DEFAULT is.
     */
    public ?string $algorithm = null;

    /**
     * The LOCK written last, in lowercase, or null when none or DEFAULT is.
     */
    public ?string $lock = null;

    /**
     * Whether an action changes the definition of the table, rather than only renaming it or choosing how to change it.
     */
    public bool $changes = false;

    /**
     * Whether an action asks for the table to be copied, as CONVERT TO CHARACTER SET and ORDER BY do.
     */
    public bool $copies = false;

    /**
     * Whether the actions include ORDER BY, which the server ignores on a table with a clustered index.
     */
    public bool $ordered = false;

    /**
     * Whether ENABLE KEYS or DISABLE KEYS is written, which InnoDB notes it does not support.
     */
    public bool $toggled = false;

    /**
     * @param TableLayout $layout The layout the actions change
     * @param string $table The name of the table, for messages
     * @param string $database The current database, which a new name without a database names
     * @param GrammarRelease $release The release whose default collations apply
     */
    public function __construct(public readonly TableLayout $layout, public readonly string $table, public readonly string $database, public readonly GrammarRelease $release)
    {
    }

    /**
     * Applies actions in written order, then places the new columns.
     *
     * @param list<AlterCommand> $commands
     *
     * @throws SqlError When an action names what the table lacks, or leaves it without columns or with a column twice
     */
    public function apply(array $commands): void
    {
        $placed = [];
        $added = [];
        foreach ($commands as $command) {
            if (!$command instanceof AlterModifier && !$command instanceof RenameTo && !$command instanceof ToggleKeys) {
                $this->changes = true;
            }
            if ($command instanceof AddConstraint) {
                $added[] = [$command->element, null];

                continue;
            }
            if ($command instanceof AddColumns) {
                foreach ($command->elements as $element) {
                    if ($element instanceof ColumnElement) {
                        $placed[] = [$element, null, null];
                    } else {
                        $added[] = [$element, null];
                    }
                }

                continue;
            }
            foreach ($this->command($command) as $entry) {
                $placed[] = $entry;
            }
        }
        $this->layout->keys = [...$this->layout->keys, ...$added];
        foreach ($placed as [$element, $position, $origin]) {
            $this->place($element, $position, $origin);
        }
        if ($this->layout->columns === []) {
            throw ErrorCode::CantRemoveAllFields->error();
        }
        $seen = [];
        foreach ($this->layout->columns as [$element]) {
            $name = mb_strtolower($element->name->column->value);
            if (isset($seen[$name])) {
                throw ErrorCode::DuplicateFieldName->error($element->name->column->value);
            }
            $seen[$name] = true;
        }
    }

    /**
     * Applies one action and answers the columns it places later, each with its position and its old position.
     *
     * @return list<array{ColumnElement, ColumnPosition|null, int|null}>
     *
     * @throws SqlError When the action names what the table lacks
     */
    public function command(AlterCommand $command): array
    {
        $layout = $this->layout;
        if ($command instanceof AddColumn) {
            return [[$command->column, $command->position, null]];
        }
        if ($command instanceof ChangeColumn) {
            $index = $this->existing(($command->column ?? $command->definition->name)->column->value);
            $origin = $layout->columns[$index][1];
            unset($this->layout->undefaulted[mb_strtolower($layout->columns[$index][0]->name->column->value)]);
            if ($command->position === null) {
                $layout->columns[$index] = [$command->definition, $origin];

                return [];
            }
            array_splice($layout->columns, $index, 1);

            return [[$command->definition, $command->position, $origin]];
        }
        if ($command instanceof DefaultSetting) {
            $index = $this->existing($command->column->column->value);
            [$element, $origin] = $layout->columns[$index];
            $attributes = array_values(array_filter($this->attributes($element), static fn (ColumnAttribute $attribute): bool => !$attribute instanceof DefaultLiteral && !$attribute instanceof DefaultExpression));
            if ($command->value !== null) {
                $attributes[] = $command->expression ? new DefaultExpression($command->value) : new DefaultLiteral($command->value);
                unset($this->layout->undefaulted[mb_strtolower($element->name->column->value)]);
            } else {
                $this->layout->undefaulted[mb_strtolower($element->name->column->value)] = true;
            }
            $layout->columns[$index] = [$this->attributed($element, $attributes), $origin];

            return [];
        }
        if ($command instanceof ColumnVisibility) {
            $index = $this->existing($command->column->column->value);
            [$element, $origin] = $layout->columns[$index];
            $attributes = array_values(array_filter($this->attributes($element), static fn (ColumnAttribute $attribute): bool => !$attribute instanceof KeywordAttribute || ($attribute->keyword !== ColumnKeyword::Visible && $attribute->keyword !== ColumnKeyword::Invisible)));
            if (!$command->visible) {
                $attributes[] = new KeywordAttribute(ColumnKeyword::Invisible);
            }
            $layout->columns[$index] = [$this->attributed($element, $attributes), $origin];

            return [];
        }
        if ($command instanceof DropElement) {
            $this->drop($command);

            return [];
        }
        if ($command instanceof RenameElement) {
            $this->rename($command);

            return [];
        }
        if ($command instanceof IndexVisibility) {
            $index = $layout->key($command->index->value);
            if ($index === null) {
                throw ErrorCode::KeyMissing->error($command->index->value, $this->table);
            }
            $key = $layout->keys[$index][0];
            if ($key instanceof IndexDefinition && $key->kind === IndexKind::Primary && !$command->visible) {
                throw ErrorCode::PrimaryKeyInvisible->error();
            }

            return [];
        }
        if ($command instanceof ConstraintEnforcement) {
            if ($this->constraint($command->constraint->value, $command->kind === ElementKind::Check) === null) {
                throw $command->kind === ElementKind::Check ? ErrorCode::CheckConstraintNotFound->error($command->constraint->value) : ErrorCode::ConstraintNotFound->error($command->constraint->value);
            }

            return [];
        }
        if ($command instanceof SetTableOptions) {
            $this->options($command->options);

            return [];
        }
        if ($command instanceof ConvertCharset) {
            $this->convert($command);

            return [];
        }
        if ($command instanceof RenameTo) {
            $layout->name = new QualifiedName($command->table->name, $command->table->schema ?? new Name($this->database));

            return [];
        }
        if ($command instanceof AlgorithmOption) {
            $this->algorithm = $command->algorithm === null || strcasecmp($command->algorithm->value, 'default') === 0 ? null : strtolower($command->algorithm->value);

            return [];
        }
        if ($command instanceof LockOption) {
            $this->lock = $command->lock === null || strcasecmp($command->lock->value, 'default') === 0 ? null : strtolower($command->lock->value);

            return [];
        }
        if ($command instanceof ToggleKeys) {
            $this->toggled = true;

            return [];
        }
        if ($command instanceof SecondaryLoad) {
            throw new SqlError(ErrorCode::SecondaryEngineFailed, 'Secondary engine operation failed. No secondary engine defined.');
        }
        if ($command instanceof TablespaceCommand || $command instanceof PartitionBy) {
            throw ErrorCode::NotSupportedYet->error('ALTER TABLE ' . ($command instanceof PartitionBy ? 'PARTITION BY' : 'TABLESPACE'));
        }
        if ($command instanceof StandaloneCommand || $command instanceof TrailingCommand) {
            throw ErrorCode::PartitionManagementOnNonpartitioned->error();
        }
        if ($command instanceof ValidationOption) {
            return [];
        }
        $this->ordered = $command instanceof Reorder || $this->ordered;
        $this->copies = $this->copies || $this->ordered;

        return [];
    }

    /**
     * Answers the index in the layout of a column the table has, or raises ER_BAD_FIELD_ERROR.
     *
     * @throws SqlError When the table has no such column
     */
    public function existing(string $name): int
    {
        $index = $this->layout->column($name);
        if ($index === null) {
            throw ErrorCode::BadField->error($name, $this->table);
        }

        return $index;
    }

    /**
     * Drops a column, an index, the primary key or a constraint.
     *
     * @throws SqlError When the table has no such element
     */
    public function drop(DropElement $command): void
    {
        $layout = $this->layout;
        $name = $command->kind === ElementKind::PrimaryKey ? 'PRIMARY' : ($command->name->column->value ?? '');
        if ($command->kind === ElementKind::Column) {
            $index = $layout->column($name);
            if ($index === null) {
                throw ErrorCode::CantDropFieldOrKey->error($name);
            }
            array_splice($layout->columns, $index, 1);

            return;
        }
        if ($command->kind === ElementKind::Index || $command->kind === ElementKind::PrimaryKey) {
            $index = $layout->key($name);
            if ($index === null) {
                throw ErrorCode::CantDropFieldOrKey->error($name);
            }
            array_splice($layout->keys, $index, 1);

            return;
        }
        $index = $this->constraint($name, $command->kind === ElementKind::Check);
        if ($index === null) {
            if ($command->kind === ElementKind::Check) {
                throw ErrorCode::CheckConstraintNotFound->error($name);
            }

            throw $command->kind === ElementKind::ForeignKey ? ErrorCode::CantDropFieldOrKey->error($name) : ErrorCode::ConstraintNotFound->error($name);
        }
        array_splice($layout->keys, $index, 1);
    }

    /**
     * Renames a column or an index.
     *
     * @throws SqlError When the table has no such element
     */
    public function rename(RenameElement $command): void
    {
        $layout = $this->layout;
        if ($command->kind === ElementKind::Column) {
            $index = $this->existing($command->from->column->value);
            [$element, $origin] = $layout->columns[$index];
            if (isset($this->layout->undefaulted[mb_strtolower($element->name->column->value)])) {
                unset($this->layout->undefaulted[mb_strtolower($element->name->column->value)]);
                $this->layout->undefaulted[mb_strtolower($command->to->column->value)] = true;
            }
            $layout->columns[$index] = [new ColumnElement(new ColumnName($command->to->column), $element->specification), $origin];

            return;
        }
        $index = $layout->key($command->from->column->value);
        if ($index === null) {
            throw ErrorCode::KeyMissing->error($command->from->column->value, $this->table);
        }
        [$key, $origin] = $layout->keys[$index];
        assert($key instanceof IndexDefinition);
        $layout->keys[$index] = [new IndexDefinition($key->kind, $key->parts, new ColumnName($command->to->column), $key->algorithm, $key->options, $key->constraint, $key->keyword), $origin];
    }

    /**
     * Answers the index in the layout of a constraint by name, or null: a check constraint, or with $check false also a foreign key.
     */
    public function constraint(string $name, bool $check): ?int
    {
        $checks = 0;
        foreach ($this->layout->keys as $index => [$element]) {
            if ($element instanceof CheckConstraint) {
                $checks++;
                $named = $element->name?->name?->column->value ?? $this->table . '_chk_' . $checks;
                if (strcasecmp($named, $name) === 0) {
                    return $index;
                }
            }
            if (!$check && $element instanceof ForeignKey && strcasecmp($element->constraint?->name?->column->value ?? $element->name?->column->value ?? '', $name) === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Places a new or moved column: at the end, first, or after a column of the new table.
     *
     * @throws SqlError When the column to follow does not exist
     */
    public function place(ColumnElement $element, ?ColumnPosition $position, ?int $origin): void
    {
        $columns = $this->layout->columns;
        if ($position === null) {
            $columns[] = [$element, $origin];
        } elseif ($position->after === null) {
            array_unshift($columns, [$element, $origin]);
        } else {
            $after = $this->layout->column($position->after->value);
            if ($after === null) {
                throw ErrorCode::BadField->error($position->after->value, $this->table);
            }
            array_splice($columns, $after + 1, 0, [[$element, $origin]]);
        }
        $this->layout->columns = $columns;
    }

    /**
     * Sets table options: an option replaces the one of its kind, and a character set or collation replaces both.
     *
     * @param list<TableOption> $options
     */
    public function options(array $options): void
    {
        $kept = $this->layout->options;
        foreach ($options as $option) {
            $kept = array_values(array_filter($kept, fn (TableOption $existing): bool => $this->kind($existing) !== $this->kind($option)));
            $kept[] = $option;
        }
        $this->layout->options = $kept;
    }

    /**
     * Answers what kind of option a table option sets, which a later option of the same kind replaces.
     */
    public function kind(TableOption $option): string
    {
        return match (true) {
            $option instanceof CharsetOption, $option instanceof CollationOption => 'collation',
            $option instanceof NumberOption => 'number ' . $option->kind->value,
            $option instanceof TextOption => 'text ' . $option->kind->value,
            default => $option::class,
        };
    }

    /**
     * Converts the table and its character columns to a character set: the default of the table and each column that is not binary.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-character-set.
     */
    public function convert(ConvertCharset $command): void
    {
        $this->copies = true;
        $charset = $command->charset->name === null ? null : Charset::named($command->charset->name->value);
        $collation = $command->collation?->name === null ? $charset?->defaultCollation($this->release) : Collation::named($command->collation->name->value);
        $this->options([new CharsetOption($command->charset), ...($command->collation === null ? [] : [new CollationOption($command->collation)])]);
        if ($collation === null) {
            return;
        }
        foreach ($this->layout->columns as $index => [$element, $origin]) {
            $specification = $element->specification;
            if (!$specification instanceof OrdinaryColumn || !$specification->type instanceof Character) {
                continue;
            }
            $type = new Character($specification->type->kind, $specification->type->length);
            $attributes = array_values(array_filter($specification->attributes, static fn (ColumnAttribute $attribute): bool => !$attribute instanceof CollateAttribute));
            $attributes[] = new CollateAttribute(new CollationName(new Name($collation->name)));
            $this->layout->columns[$index] = [new ColumnElement($element->name, new OrdinaryColumn($type, $attributes, $specification->references)), $origin];
        }
    }

    /**
     * Answers the attributes of a column definition.
     *
     * @return list<ColumnAttribute>
     */
    public function attributes(ColumnElement $element): array
    {
        $specification = $element->specification;

        return $specification instanceof OrdinaryColumn || $specification instanceof GeneratedColumn ? $specification->attributes : [];
    }

    /**
     * Answers a column definition with other attributes.
     *
     * @param list<ColumnAttribute> $attributes
     */
    public function attributed(ColumnElement $element, array $attributes): ColumnElement
    {
        $specification = $element->specification;
        if (!$specification instanceof OrdinaryColumn && !$specification instanceof GeneratedColumn) {
            return $element;
        }

        return new ColumnElement($element->name, TableLayout::specified($specification, $attributes));
    }
}
