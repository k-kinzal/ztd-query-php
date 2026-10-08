<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\StatementError;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Column\CollateAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * The definition of a table as the elements of a CREATE TABLE statement, which a change of the table rewrites.
 *
 * Each column is its column definition, with the position the column had before the change, and
 * each index is an index definition. A column definition holds no PRIMARY KEY or UNIQUE
 * attribute, since the keys of the table are its index definitions, and a string column states
 * its collation, so that a new default character set of the table leaves the column as it is,
 * and states NOT NULL when it is, so that dropping a primary key leaves its columns NOT NULL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility MySqlMemory
 */
final class TableLayout
{
    /**
     * The columns, by lowercase name, that have no default although a definition of their type and nullability implies one, as after DROP DEFAULT.
     *
     * @var array<string, true>
     */
    public array $undefaulted = [];

    /**
     * The name of each column of the table before the change, by its position.
     *
     * @var array<int, string>
     */
    public array $names = [];

    /**
     * @param QualifiedName $name The table name with its database
     * @param list<array{ColumnElement, int|null}> $columns Each column definition, with the position of the column before the change; null for a new column
     * @param list<array{TableElement, Key|null}> $keys The index definitions and the other constraints in order, each index with the key of the table it was before the change; null for a new one
     * @param list<TableOption> $options The table options in order
     * @param int $temporaryWords How many times TEMPORARY is written
     */
    public function __construct(public QualifiedName $name, public array $columns, public array $keys, public array $options, public readonly int $temporaryWords = 0)
    {
    }

    /**
     * Answers the layout of a stored table.
     *
     * @throws \MySqlMemory\Error\SqlError When the statement that declares the table is not known
     */
    public static function of(TableDefinition $definition): self
    {
        $statement = $definition->statement;
        if ($statement === null) {
            throw StatementError::NotSupportedYet->error('ALTER TABLE of a table created by a query');
        }
        $elements = [];
        foreach ($statement->elements as $element) {
            if ($element instanceof ColumnElement) {
                $elements[strtolower($element->name->column->value)] = $element;
            }
        }
        $columns = [];
        $undefaulted = [];
        foreach ($definition->columns as $position => $column) {
            $element = $elements[strtolower($column->name)] ?? null;
            if ($element === null) {
                throw StatementError::NotSupportedYet->error('ALTER TABLE of a table created by a query');
            }
            $collation = $column->domain->kind === Kind::String && !$column->domain->collation->bytes() ? $column->domain->collation->name : null;
            $columns[] = [self::unkeyed($element, $collation, !$column->nullable()), $position];
            if (!$column->default->declared && $column->nullable() && !$column->autoIncrement) {
                $undefaulted[mb_strtolower($column->name)] = true;
            }
        }
        $keys = array_map(static fn (Key $key): array => [self::index($key, $definition), $key], $definition->keys);
        foreach ($statement->elements as $element) {
            if (!$element instanceof ColumnElement && !$element instanceof IndexDefinition) {
                $keys[] = [$element, null];
            }
        }

        $options = array_values(array_filter($statement->options, static fn (TableOption $option): bool => !$option instanceof CharsetOption && !$option instanceof CollationOption));
        $options[] = new CollationOption(new CollationName(new Name($definition->collation)));

        $layout = new self(new QualifiedName(new Name($definition->name), new Name($definition->schema)), $columns, $keys, $options, $statement->temporaryWords);
        $layout->undefaulted = $undefaulted;
        $layout->names = array_map(static fn ($column): string => $column->name, $definition->columns);

        return $layout;
    }

    /**
     * Answers a column definition without the PRIMARY KEY and UNIQUE attributes, stating its collation when it has none, and NOT NULL when the column is.
     *
     * A column of a primary key is NOT NULL without saying so, and stays NOT NULL when the key is dropped.
     */
    public static function unkeyed(ColumnElement $element, ?string $collation, bool $notNull = false): ColumnElement
    {
        $specification = $element->specification;
        if (!$specification instanceof OrdinaryColumn && !$specification instanceof GeneratedColumn) {
            return $element;
        }
        $attributes = array_values(array_filter($specification->attributes, static fn (ColumnAttribute $attribute): bool => !$attribute instanceof KeywordAttribute || ($attribute->keyword !== ColumnKeyword::PrimaryKey && $attribute->keyword !== ColumnKeyword::Unique)));
        $collated = array_filter($attributes, static fn (ColumnAttribute $attribute): bool => $attribute instanceof CollateAttribute) !== [];
        if ($collation !== null && !$collated && !($specification instanceof GeneratedColumn && $specification->collation !== null)) {
            $attributes[] = new CollateAttribute(new CollationName(new Name($collation)));
        }
        if ($notNull && array_filter($attributes, static fn (ColumnAttribute $attribute): bool => $attribute instanceof KeywordAttribute && $attribute->keyword === ColumnKeyword::NotNull) === []) {
            $attributes[] = new KeywordAttribute(ColumnKeyword::NotNull);
        }

        return new ColumnElement($element->name, self::specified($specification, $attributes));
    }

    /**
     * Answers a column specification with other attributes.
     *
     * @param list<ColumnAttribute> $attributes
     */
    public static function specified(OrdinaryColumn|GeneratedColumn $specification, array $attributes): OrdinaryColumn|GeneratedColumn
    {
        if ($specification instanceof GeneratedColumn) {
            return new GeneratedColumn($specification->type, $specification->expression, $specification->collation, $specification->storage, $attributes, $specification->references);
        }

        return new OrdinaryColumn($specification->type, $attributes, $specification->references);
    }

    /**
     * Answers the index definition of a key of a table.
     */
    public static function index(Key $key, TableDefinition $definition): IndexDefinition
    {
        $parts = [];
        foreach ($key->columns as $index => $position) {
            $prefix = $key->prefixes[$index] ?? null;
            $parts[] = new ColumnPart(new Name($definition->columns[$position]->name), $prefix === null ? null : new Numeral((string) $prefix), ($key->descending[$index] ?? false) ? Direction::Descending : null);
        }
        $kind = match ($key->kind) {
            KeyKind::Primary => IndexKind::Primary,
            KeyKind::Unique => IndexKind::Unique,
            KeyKind::Index => IndexKind::Index,
            KeyKind::FullText => IndexKind::FullText,
            KeyKind::Spatial => IndexKind::Spatial,
        };

        return new IndexDefinition($kind, $parts, $kind === IndexKind::Primary ? null : new ColumnName(new Name($key->name)));
    }

    /**
     * Answers the index in the layout of a column by name, compared without regard to case, or null.
     */
    public function column(string $name): ?int
    {
        foreach ($this->columns as $index => [$element]) {
            if (strcasecmp($element->name->column->value, $name) === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Answers the name a column of the table before the change has in the layout: the column it became, else a new column of its name; null when it is gone.
     */
    public function covered(int $position): ?string
    {
        foreach ($this->columns as [$column, $origin]) {
            if ($origin === $position) {
                return $column->name->column->value;
            }
        }
        foreach ($this->columns as [$column, $origin]) {
            if ($origin === null && strcasecmp($column->name->column->value, $this->names[$position] ?? '') === 0) {
                return $column->name->column->value;
            }
        }

        return null;
    }

    /**
     * Answers the index in the layout of an index definition by name, compared without regard to case, or null; PRIMARY names the primary key.
     */
    public function key(string $name): ?int
    {
        foreach ($this->keys as $index => [$key]) {
            if (!$key instanceof IndexDefinition) {
                continue;
            }
            $named = $key->kind === IndexKind::Primary ? 'PRIMARY' : ($key->name?->column->value ?? '');
            if (strcasecmp($named, $name) === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Answers the CREATE TABLE statement that declares the table of the layout.
     *
     * An index the table had covers the columns it covered under their new names, or a new column
     * of the name of a column it covered; a column dropped leaves the index, and an index without
     * columns is dropped.
     */
    public function statement(): CreateTable
    {
        $elements = array_map(static fn (array $column): ColumnElement => $column[0], $this->columns);
        foreach ($this->keys as [$element, $key]) {
            if ($key === null || !$element instanceof IndexDefinition) {
                $elements[] = $element;

                continue;
            }
            $parts = [];
            foreach ($key->columns as $index => $position) {
                $name = $this->covered($position);
                if ($name !== null) {
                    $prefix = $key->prefixes[$index] ?? null;
                    $parts[] = new ColumnPart(new Name($name), $prefix === null ? null : new Numeral((string) $prefix), ($key->descending[$index] ?? false) ? Direction::Descending : null);
                }
            }
            if ($parts !== []) {
                $elements[] = new IndexDefinition($element->kind, $parts, $element->name, $element->algorithm, $element->options, $element->constraint, $element->keyword);
            }
        }

        return new CreateTable($this->name, $elements, $this->options, null, null, $this->temporaryWords);
    }
}
