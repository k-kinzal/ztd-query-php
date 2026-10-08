<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\ForeignKey;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey as ForeignKeyElement;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Builds the foreign keys a CREATE TABLE statement declares, and the index each needs.
 *
 * A foreign key without a name is named `<table>_ibfk_<n>`, numbered in written order among the
 * unnamed ones. The referenced table must be an InnoDB base table, the referenced columns must
 * exist, be as many as the referencing ones and of a compatible type, and form a unique key in
 * that order (from 8.4 on; an index that starts with them before), and a SET NULL action needs
 * columns that admit NULL. A temporary table has no foreign keys. The child table gets an index
 * on the referencing columns unless one starts with them, named after the index or constraint
 * name written, else after its first column; an index the server added that another index starts
 * with is dropped. A REFERENCES clause written after a column declares nothing (verified on live
 * 8.0.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html.
 *
 * @visibility MySqlMemory
 */
final class ForeignKeys
{
    /**
     * @param Planner $planner The planner of the statement
     * @param CreateTable $create The statement that declares the table
     * @param int $base The number the first unnamed foreign key is numbered after
     * @param array<string, true> $kept The foreign keys, by lowercase name, a changed table had, which are not checked against the table they reference again
     */
    public function __construct(public readonly Planner $planner, public readonly CreateTable $create, public readonly int $base = 0, public readonly array $kept = [])
    {
    }

    /**
     * Answers each foreign key the statement writes, in written order, with its name and whether the server named it.
     *
     * @return list<array{ForeignKeyElement, string, bool}>
     */
    public function written(): array
    {
        $table = $this->create->name->name->value;
        $number = $this->base;
        $written = [];
        foreach ($this->create->elements as $element) {
            if ($element instanceof ForeignKeyElement) {
                $name = $element->constraint?->name?->column->value;
                $written[] = [$element, $name ?? $table . '_ibfk_' . ++$number, $name === null || Constraints::generated($name, $table, '_ibfk_')];
            }
        }

        return $written;
    }

    /**
     * Answers the index a foreign key needs on the referencing columns, named after the index or constraint name written, or unnamed.
     *
     * @param list<ColumnDefinition> $columns
     *
     * @throws SqlError When a referencing column is not a column of the table
     */
    public function implicit(ForeignKeyElement $element, array $columns): Key
    {
        $positions = [];
        foreach ($element->columns as $part) {
            $position = null;
            foreach ($columns as $index => $column) {
                if (strcasecmp($column->name, $part->column->value) === 0) {
                    $position = $index;
                }
            }
            $positions[] = $position ?? throw SchemaError::KeyColumnMissing->error($part->column->value);
        }

        return new Key($element->name?->column->value ?? $element->constraint?->name?->column->value ?? '', KeyKind::Index, $positions, [], [], true);
    }

    /**
     * Drops each index added for a foreign key that another index starts with.
     *
     * @param list<Key> $keys All the keys, in written order
     * @param list<Key> $implicit The keys added for foreign keys
     * @return list<Key>
     */
    public function pruned(array $keys, array $implicit): array
    {
        foreach ($implicit as $added) {
            foreach ($keys as $key) {
                if ($key !== $added && self::starts($key, $added->columns)) {
                    $keys = array_values(array_filter($keys, static fn (Key $kept): bool => $kept !== $added));
                    break;
                }
            }
        }

        return $keys;
    }

    /**
     * Tells whether a key starts with whole columns.
     *
     * @param list<int> $columns
     */
    public static function starts(Key $key, array $columns): bool
    {
        if ($key->kind === KeyKind::FullText || $key->kind === KeyKind::Spatial || array_slice($key->columns, 0, count($columns)) !== $columns) {
            return false;
        }
        foreach (array_keys($columns) as $index) {
            if (($key->prefixes[$index] ?? null) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Answers the foreign keys of a definition, checked against the tables they reference.
     *
     * @return list<ForeignKey>
     *
     * @throws SqlError When a foreign key is invalid
     */
    public function built(TableDefinition $definition): array
    {
        $keys = [];
        $names = [];
        foreach ($this->written() as [$element, $name, $generated]) {
            if (isset($names[mb_strtolower($name)])) {
                throw ConstraintError::DuplicateForeignName->error($name);
            }
            $names[mb_strtolower($name)] = true;
            $keys[] = $this->checked($element, $name, $generated, $definition);
        }

        return $keys;
    }

    /**
     * Answers one foreign key, checked against the table it references.
     *
     * @throws SqlError When the foreign key is invalid
     */
    public function checked(ForeignKeyElement $element, string $name, bool $generated, TableDefinition $definition): ForeignKey
    {
        if ($definition->temporary) {
            throw ConstraintError::CannotAddForeign->error();
        }
        $columns = $this->implicit($element, $definition->columns)->columns;
        $written = $element->references->columns ?? [];
        if (count($written) !== count($columns)) {
            throw ConstraintError::WrongForeignKey->error($element->constraint?->name?->column->value ?? 'foreign key without name', "Key reference and table reference don't match");
        }
        $reference = $element->references->table;
        $schema = $reference->schema->value ?? $definition->schema;
        $parent = $schema === $definition->schema && $reference->name->value === $definition->name ? $definition : $this->planner->dictionary->schema($schema)?->table($reference->name->value)?->definition;
        if ($parent === null && (isset($this->kept[mb_strtolower($name)]) || $this->planner->compiler->connection->variables->read('foreign_key_checks') === 'OFF')) {
            return $this->unchecked($element, $name, $generated, $columns, $schema);
        }
        if ($parent === null || strcasecmp($parent->engine, 'InnoDB') !== 0) {
            throw ConstraintError::ForeignTableMissing->error($reference->name->value);
        }
        $referenced = [];
        foreach ($written as $index => $column) {
            $position = $parent->position($column->value) ?? throw ConstraintError::ForeignColumnMissing->error($column->value, $name, $reference->name->value);
            if (!$this->compatible($definition->columns[$columns[$index]], $parent->columns[$position])) {
                throw ConstraintError::ForeignIncompatible->error($definition->columns[$columns[$index]]->name, $parent->columns[$position]->name, $name);
            }
            $referenced[] = $position;
        }
        $actions = [ReferenceEvent::Delete->value => null, ReferenceEvent::Update->value => null];
        foreach ($element->references->actions as $action) {
            $actions[$action->event->value] = $action->option;
            $this->action($action->event, $action->option, $columns, $definition, $name);
        }
        $this->indexed($parent, $referenced, $name, $reference->name->value);

        return new ForeignKey($name, $columns, $schema, $parent->name, array_map(static fn (int $position): string => $parent->columns[$position]->name, $referenced), $actions[ReferenceEvent::Delete->value], $actions[ReferenceEvent::Update->value], $generated);
    }

    /**
     * Answers a foreign key whose referenced table does not exist, as foreign_key_checks off lets a table declare, and as a table keeps it when the referenced table is dropped.
     *
     * @param list<int> $columns
     */
    public function unchecked(ForeignKeyElement $element, string $name, bool $generated, array $columns, string $schema): ForeignKey
    {
        $actions = [ReferenceEvent::Delete->value => null, ReferenceEvent::Update->value => null];
        foreach ($element->references->actions as $action) {
            $actions[$action->event->value] = $action->option;
        }

        return new ForeignKey($name, $columns, $schema, $element->references->table->name->value, array_map(static fn ($column): string => $column->value, $element->references->columns ?? []), $actions[ReferenceEvent::Delete->value], $actions[ReferenceEvent::Update->value], $generated);
    }

    /**
     * Refuses an action the referencing columns cannot take: SET NULL on a NOT NULL column, and an action that changes a generated column.
     *
     * @param list<int> $columns
     *
     * @throws SqlError When the action is refused
     */
    public function action(ReferenceEvent $event, ReferenceOption $option, array $columns, TableDefinition $definition, string $name): void
    {
        foreach ($columns as $position) {
            $column = $definition->columns[$position];
            if ($column->generated !== null && in_array($option, [ReferenceOption::SetNull, ReferenceOption::Cascade, ReferenceOption::SetDefault], true)) {
                throw ConstraintError::GeneratedForeignAction->error('ON ' . $event->value . ' ' . $option->value);
            }
            if ($option !== ReferenceOption::Restrict && $option !== ReferenceOption::NoAction && $this->stored($column->name)) {
                throw ConstraintError::CannotAddForeign->error();
            }
            if ($option === ReferenceOption::SetNull && !$column->nullable()) {
                throw ConstraintError::ForeignNotNullSetNull->error($column->name, $name);
            }
        }
    }

    /**
     * Tells whether a STORED generated column of the statement reads a column, which a cascading action may not change.
     */
    public function stored(string $name): bool
    {
        foreach ($this->create->elements as $element) {
            $specification = $element instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition ? $element->specification : null;
            if (!$specification instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn || $specification->storage !== \SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage::Stored) {
                continue;
            }
            foreach ((new \MySqlMemory\Evaluation\Compile\Walker())->find($specification->expression, \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse::class) as $use) {
                if (strcasecmp($use->name->value, $name) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Refuses referenced columns that no key of the referenced table indexes as a foreign key needs: from 8.4 on a unique key of exactly these columns in order, before an index that starts with them.
     *
     * @param list<int> $referenced
     *
     * @throws SqlError When no key indexes them
     */
    public function indexed(TableDefinition $parent, array $referenced, string $name, string $table): void
    {
        $release = $this->planner->settings->release();
        $strict = in_array($release, [GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910], true);
        foreach ($parent->keys as $key) {
            if ($strict ? $key->unique() && $key->columns === $referenced && self::starts($key, $referenced) : self::starts($key, $referenced)) {
                return;
            }
        }
        if ($strict) {
            throw ConstraintError::ForeignMissingKey->error($name, $table);
        }

        throw in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? ConstraintError::CannotAddForeign->error() : ConstraintError::ForeignMissingIndex->error($name, $table);
    }

    /**
     * Tells whether a referencing column can reference a referenced one: the same type, sign and scale, and for text the same collation; strings of different lengths can.
     */
    public function compatible(ColumnDefinition $child, ColumnDefinition $parent): bool
    {
        $from = $child->domain;
        $to = $parent->domain;
        if ($from->kind !== $to->kind || $from->unsigned !== $to->unsigned) {
            return false;
        }

        return match ($from->kind) {
            Kind::String => $from->collation->name === $to->collation->name && $from->field->blob() === $to->field->blob() && ($from->field === $to->field || !in_array(true, [$from->field->blob(), in_array($from->field, [\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Enum, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Set], true), in_array($to->field, [\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Enum, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Set], true)], true)),
            Kind::Decimal => $from->precision() === $to->precision() && $from->decimals === $to->decimals,
            Kind::Integer, Kind::Double, Kind::Date, Kind::DateTime, Kind::Time, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => $from->field === $to->field && $from->decimals === $to->decimals,
        };
    }
}
