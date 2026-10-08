<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Error\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Error\StatementError;
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
     * The actions on single columns, applied to the same layout.
     */
    public readonly ColumnChange $columns;

    /**
     * @param TableLayout $layout The layout the actions change
     * @param string $table The name of the table, for messages
     * @param string $database The current database, which a new name without a database names
     * @param GrammarRelease $release The release whose default collations apply
     */
    public function __construct(public readonly TableLayout $layout, public readonly string $table, public readonly string $database, public readonly GrammarRelease $release)
    {
        $this->columns = new ColumnChange($layout, $table);
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
            $this->columns->place($element, $position, $origin);
        }
        if ($this->layout->columns === []) {
            throw SchemaError::CantRemoveAllFields->error();
        }
        $seen = [];
        foreach ($this->layout->columns as [$element]) {
            $name = mb_strtolower($element->name->column->value);
            if (isset($seen[$name])) {
                throw SchemaError::DuplicateFieldName->error($element->name->column->value);
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
        if ($command instanceof AddColumn) {
            return [[$command->column, $command->position, null]];
        }
        if ($command instanceof ChangeColumn) {
            return $this->columns->change($command);
        }
        if ($command instanceof DefaultSetting) {
            $this->columns->alterDefault($command);
        } elseif ($command instanceof ColumnVisibility) {
            $this->columns->alterVisibility($command);
        } else {
            $this->act($command);
        }

        return [];
    }

    /**
     * Applies an action that places no column: a drop, a rename, a change of the visibility of an
     * index or of the enforcement of a constraint, table options, CONVERT TO CHARACTER SET or
     * RENAME TO, and otherwise an action that chooses how the table is changed.
     *
     * @throws SqlError When the action names what the table lacks, or the server refuses it
     */
    public function act(AlterCommand $command): void
    {
        if ($command instanceof DropElement) {
            $this->drop($command);
        } elseif ($command instanceof RenameElement) {
            $this->rename($command);
        } elseif ($command instanceof IndexVisibility) {
            $index = $this->layout->key($command->index->value);
            if ($index === null) {
                throw SchemaError::KeyMissing->error($command->index->value, $this->table);
            }
            $key = $this->layout->keys[$index][0];
            if ($key instanceof IndexDefinition && $key->kind === IndexKind::Primary && !$command->visible) {
                throw SchemaError::PrimaryKeyInvisible->error();
            }
        } elseif ($command instanceof ConstraintEnforcement) {
            if ($this->constraint($command->constraint->value, $command->kind === ElementKind::Check) === null) {
                throw $command->kind === ElementKind::Check ? SchemaError::CheckConstraintNotFound->error($command->constraint->value) : SchemaError::ConstraintNotFound->error($command->constraint->value);
            }
        } elseif ($command instanceof SetTableOptions) {
            $this->options($command->options);
        } elseif ($command instanceof ConvertCharset) {
            $this->convert($command);
        } elseif ($command instanceof RenameTo) {
            $this->layout->name = new QualifiedName($command->table->name, $command->table->schema ?? new Name($this->database));
        } else {
            $this->manner($command);
        }
    }

    /**
     * Applies an action that chooses how the server changes the table: ALGORITHM, LOCK, ENABLE or
     * DISABLE KEYS, WITH or WITHOUT VALIDATION and ORDER BY; a partition operation, or a secondary
     * engine load, is refused.
     *
     * @throws SqlError When the action is a partition operation or a secondary engine load
     */
    public function manner(AlterCommand $command): void
    {
        if ($command instanceof AlgorithmOption) {
            $this->algorithm = $command->algorithm === null || strcasecmp($command->algorithm->value, 'default') === 0 ? null : strtolower($command->algorithm->value);
        } elseif ($command instanceof LockOption) {
            $this->lock = $command->lock === null || strcasecmp($command->lock->value, 'default') === 0 ? null : strtolower($command->lock->value);
        } elseif ($command instanceof ToggleKeys) {
            $this->toggled = true;
        } elseif ($command instanceof SecondaryLoad) {
            throw new SqlError(SchemaError::SecondaryEngineFailed, 'Secondary engine operation failed. No secondary engine defined.');
        } elseif ($command instanceof TablespaceCommand || $command instanceof PartitionBy) {
            throw StatementError::NotSupportedYet->error('ALTER TABLE ' . ($command instanceof PartitionBy ? 'PARTITION BY' : 'TABLESPACE'));
        } elseif ($command instanceof StandaloneCommand || $command instanceof TrailingCommand) {
            throw SchemaError::PartitionManagementOnNonpartitioned->error();
        } elseif (!$command instanceof ValidationOption) {
            $this->ordered = $command instanceof Reorder || $this->ordered;
            $this->copies = $this->copies || $this->ordered;
        }
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
                throw SchemaError::CantDropFieldOrKey->error($name);
            }
            array_splice($layout->columns, $index, 1);

            return;
        }
        if ($command->kind === ElementKind::Index || $command->kind === ElementKind::PrimaryKey) {
            $index = $layout->key($name);
            if ($index === null) {
                throw SchemaError::CantDropFieldOrKey->error($name);
            }
            array_splice($layout->keys, $index, 1);

            return;
        }
        $index = $this->constraint($name, $command->kind === ElementKind::Check);
        if ($index === null) {
            if ($command->kind === ElementKind::Check) {
                throw SchemaError::CheckConstraintNotFound->error($name);
            }

            throw $command->kind === ElementKind::ForeignKey ? SchemaError::CantDropFieldOrKey->error($name) : SchemaError::ConstraintNotFound->error($name);
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
            $this->columns->rename($command);

            return;
        }
        $index = $layout->key($command->from->column->value);
        if ($index === null) {
            throw SchemaError::KeyMissing->error($command->from->column->value, $this->table);
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
}
