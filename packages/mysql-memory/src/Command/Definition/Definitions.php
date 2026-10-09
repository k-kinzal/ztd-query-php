<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Definition\Constraint\Constraints;
use MySqlMemory\Command\Definition\Constraint\ForeignKeys;
use MySqlMemory\Command\Definition\Expression\TableExpressions;
use MySqlMemory\Command\Definition\Partition\PartitionDefinitions;
use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Clock;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Storage\Store;
use MySqlMemory\Typing\Declared;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Column\CollateAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\CommentAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OnUpdate;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey as ForeignKeyElement;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Builds the definition of a table a CREATE TABLE statement declares: its columns, their domains and defaults, and its keys.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility MySqlMemory
 */
final class Definitions
{
    /**
     * @param Planner $planner The planner of the statement, to compile defaults and generated columns
     * @param string $schemaCollation The default collation of the database
     */
    public function __construct(public readonly Planner $planner, public readonly string $schemaCollation)
    {
    }

    /**
     * The number the server names the first unnamed CHECK constraint after, as `<table>_chk_<n+1>`: 0 for a new table, the highest it numbered for a table ALTER TABLE changes.
     */
    public int $checkBase = 0;

    /**
     * The number the server names the first unnamed foreign key after, as `<table>_ibfk_<n+1>`.
     */
    public int $foreignBase = 0;

    /**
     * @var array<string, true> The indexes, by lowercase name, the server added for foreign keys of the table ALTER TABLE changes, which an index a foreign key needs may replace
     */
    public array $generatedKeys = [];

    /**
     * @var array<string, true> The foreign keys, by lowercase name, the table ALTER TABLE changes had, which are kept without checking the table they reference again
     */
    public array $keptForeign = [];

    /**
     * Builds the definition of a table.
     *
     * @throws \MySqlMemory\Error\SqlError When the definition is invalid
     */
    public function table(CreateTable $create, Table $declaration, string $schema): TableDefinition
    {
        $declaration = new Table(new \SqlSemantics\Statement\Identifier\QualifiedName($declaration->name->name, new \SqlSemantics\Statement\Identifier\Name($schema)), $declaration->profile, $declaration->columns, $declaration->implicit, $declaration->complete, $declaration->kind, $declaration->keys, $declaration->partitions);
        $collation = $this->collation($create);
        $engine = $this->engine($create);
        $elements = array_values(array_filter($create->elements, static fn ($element): bool => $element instanceof ColumnElement));
        $scope = new Scope();
        $declared = new Declared($collation, $this->planner->settings->release());
        $columns = [];
        $keys = [];
        foreach ($elements as $position => $element) {
            $columns[] = $this->column($element, $this->declared($declaration, $element->name->column->value), $declared, $scope, $create);
            foreach ($this->inlineKeys($element, $position) as $key) {
                $keys[] = $key;
            }
        }
        $expressions = new TableExpressions($this->planner, $create);
        $expressions->forbidden();
        $constraints = new Constraints($this->planner, $create, $this->checkBase, $this->foreignBase, $this->keptForeign);
        $constraints->forbidden();
        $foreign = new ForeignKeys($this->planner, $create);
        $implicit = [];
        foreach ($create->elements as $element) {
            if ($element instanceof IndexDefinition && isset($this->generatedKeys[mb_strtolower($element->name->column->value ?? '')])) {
                $key = $this->key($element, $columns);
                $keys[] = $implicit[] = new Key($key->name, $key->kind, $key->columns, $key->prefixes, $key->descending, true);
            } elseif ($element instanceof IndexDefinition) {
                $keys[] = $this->key($element, $columns);
            }
            if ($element instanceof ForeignKeyElement) {
                $keys[] = $implicit[] = $foreign->implicit($element, $columns);
            }
        }
        $keys = $this->named($foreign->pruned($keys, $implicit), $columns);
        $this->check($columns, $keys);
        $expressions->keyed($columns, $keys);
        usort($keys, static fn (Key $left, Key $right): int => ($right->kind === KeyKind::Primary) <=> ($left->kind === KeyKind::Primary));
        $definition = new TableDefinition($schema, $create->name->name->value, $columns, $keys, $declaration, $engine, $collation->name, $create->temporaryWords > 0, '', $create);
        $definition = $expressions->computed($definition, $this->planner->settings->connectionCollation->charset->name);

        $definition = $constraints->constrained($definition);
        if ($create->partitioning instanceof PartitionClause) {
            $definition = $definition->withPartitioning((new PartitionDefinitions($this->planner, $expressions->scope($definition)))->partitioning($create->partitioning, $definition));
        }

        return $definition;
    }

    /**
     * Answers the storage engine a table names: the last ENGINE option, or InnoDB when it names none.
     */
    public function engine(CreateTable $create): string
    {
        return (new StorageOptions())->engine($create, $this->planner->compiler->connection->variables);
    }

    /**
     * Answers the column of a declaration a column definition declares: a visible column, or the implicit column an INVISIBLE one is.
     *
     * The declaration holds the visible columns apart from the invisible ones, so a column is found
     * by its name, not by its position among the definitions.
     */
    public function declared(Table $declaration, string $name): ?\SqlSemantics\Statement\Declaration\Column
    {
        foreach ($declaration->columns as $column) {
            if (strcasecmp($column->name->value, $name) === 0) {
                return $column;
            }
        }
        foreach ($declaration->implicit as $implicit) {
            if (strcasecmp($implicit->column->name->value, $name) === 0) {
                return $implicit->column;
            }
        }

        return null;
    }

    /**
     * Answers the default collation of the table: its option, else the database's.
     */
    public function collation(CreateTable $create): Collation
    {
        $collation = Collation::named($this->schemaCollation) ?? Collation::known('utf8mb4_0900_ai_ci');
        foreach ($create->options as $option) {
            if ($option instanceof CharsetOption && $option->charset->name !== null) {
                $collation = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named($option->charset->name->value)?->defaultCollation($this->planner->settings->release()) ?? $collation;
            }
            if ($option instanceof CollationOption && $option->collation->name !== null) {
                $collation = Collation::named($option->collation->name->value) ?? $collation;
            }
        }

        return $collation;
    }

    /**
     * Builds the definition of one column.
     */
    public function column(ColumnElement $element, ?\SqlSemantics\Statement\Declaration\Column $declaration, Declared $declared, Scope $scope, CreateTable $create): ColumnDefinition
    {
        $specification = $element->specification;
        $attributes = $specification instanceof OrdinaryColumn || $specification instanceof GeneratedColumn ? $specification->attributes : [];
        $collation = null;
        $comment = '';
        foreach ($attributes as $attribute) {
            if ($attribute instanceof CollateAttribute && $attribute->collation->name !== null) {
                $collation = Collation::named($attribute->collation->name->value);
            }
            if ($attribute instanceof CommentAttribute) {
                $comment = $attribute->comment->value;
            }
        }
        if ($specification instanceof GeneratedColumn && $specification->collation?->name !== null) {
            $collation = Collation::named($specification->collation->name->value);
        }
        $resolved = $declaration?->type;
        $domain = $resolved instanceof Resolved ? $this->members(Domain::of($resolved, true), $specification->dataType()) : $declared->domain($specification->dataType(), $collation);
        (new EnumerationMembers())->check($domain, $element->name->column->value, $this->planner->compiler->connection->context);
        $nullable = $declaration === null ? true : $declaration->nullability !== Nullability::NotNull;
        $serial = $specification->dataType() instanceof Elementary && $specification->dataType()->kind === ElementaryKind::Serial;
        $domain = $domain->withNullable($nullable && !$serial);
        $keywords = array_map(static fn ($attribute) => $attribute->keyword, array_values(array_filter($attributes, static fn ($attribute): bool => $attribute instanceof KeywordAttribute)));
        $name = $element->name->column->value;
        $column = new ColumnDefinition($name, $domain, Fill::none(), $serial || in_array(ColumnKeyword::AutoIncrement, $keywords, true), false, null, in_array(ColumnKeyword::Invisible, $keywords, true), $declaration, $comment);

        return new ColumnDefinition($name, $domain, $this->default($attributes, $column, $create), $column->autoIncrement, $this->onUpdate($attributes), null, $column->invisible, $declaration, $comment);
    }

    /**
     * Answers the domain of an ENUM or SET column with its members in the character set of the column, the length counted in it.
     */
    public function members(Domain $domain, ?\SqlSemantics\Statement\Type\TypeDescriptor $type = null): Domain
    {
        if ($type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Enumeration) {
            return (new Declared($domain->collation, $this->planner->settings->release()))->enumeration($type, $domain->collation);
        }
        $charset = $domain->collation->charset;
        if ($domain->members === [] || Encoding::utf8($charset)) {
            return $domain;
        }
        $members = array_map(static fn (string $member): string => Encoding::convert($member, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'), $charset), $domain->members);
        $lengths = array_map(static fn (string $member): int => Encoding::length($member, $charset), $members);
        $length = $domain->field === Field::Enum ? max([0, ...$lengths]) : array_sum($lengths) + count($members) - 1;

        return new Domain($domain->kind, $domain->field, $length, $domain->decimals, $domain->unsigned, $domain->collation, $domain->nullable, $members, $domain->coercibility, $domain->numericBytes, $domain->display);
    }

    /**
     * Answers the default of a column from its attributes.
     *
     * CURRENT_TIMESTAMP and its synonyms are the time of each statement that stores the
     * default, not of the CREATE TABLE: the default keeps the clock, typed as SQL Semantics
     * resolved it, and evaluates it per row. An expression default, which may read the other
     * columns of the row, is compiled with the table (TableExpressions).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/timestamp-initialization.html.
     *
     * @param list<object> $attributes
     *
     * @throws \MySqlMemory\Error\SqlError When the column cannot take the default
     */
    public function default(array $attributes, ColumnDefinition $column, CreateTable $create): Fill
    {
        foreach ($attributes as $attribute) {
            if ($attribute instanceof DefaultExpression) {
                return Fill::none();
            }
            if ($attribute instanceof DefaultLiteral) {
                $value = $attribute->value;
                if (in_array($column->domain->field, [Field::Blob, Field::Json], true)) {
                    throw SchemaError::BlobCantHaveDefault->error($column->name);
                }
                $evaluable = $this->planner->compiler->compile($value, new Scope());
                if (($evaluable instanceof Retyped ? $evaluable->evaluable : $evaluable) instanceof Clock) {
                    return new Fill(true, null, $evaluable, true, 'CURRENT_TIMESTAMP' . ($column->domain->decimals > 0 ? '(' . $column->domain->decimals . ')' : ''));
                }
                $frame = new Frame($this->planner->compiler->connection->context);
                $raw = $evaluable->evaluate($frame);
                if ($raw === null && !$column->nullable()) {
                    throw SchemaError::InvalidDefault->error($column->name);
                }
                $context = $frame->context;
                $strict = $context->strict;
                $context->strict = true;
                try {
                    $stored = (new Store($context))->value($raw, $evaluable->domain(), $column);
                } catch (\MySqlMemory\Error\SqlError $error) {
                    throw new \MySqlMemory\Error\SqlError(SchemaError::InvalidDefault, SchemaError::InvalidDefault->message($column->name), $error);
                } finally {
                    $context->strict = $strict;
                }

                return Fill::constant($stored, $raw === null ? null : (string) $stored);
            }
        }

        return $column->nullable() && !$column->autoIncrement ? Fill::constant(null, null) : Fill::none();
    }

    /**
     * Tells whether a column is set to the current time when its row is updated.
     *
     * @param list<object> $attributes
     */
    public function onUpdate(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            if ($attribute instanceof OnUpdate) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the keys a column definition declares itself: PRIMARY KEY and UNIQUE.
     *
     * @return list<Key>
     */
    public function inlineKeys(ColumnElement $element, int $position): array
    {
        $specification = $element->specification;
        $keys = [];
        foreach ($specification instanceof OrdinaryColumn || $specification instanceof GeneratedColumn ? $specification->attributes : [] as $attribute) {
            if ($attribute instanceof KeywordAttribute && $attribute->keyword === ColumnKeyword::PrimaryKey) {
                $keys[] = new Key('PRIMARY', KeyKind::Primary, [$position]);
            }
            if ($attribute instanceof KeywordAttribute && $attribute->keyword === ColumnKeyword::Unique) {
                $keys[] = new Key('', KeyKind::Unique, [$position]);
            }
        }
        if ($specification->dataType() instanceof Elementary && $specification->dataType()->kind === ElementaryKind::Serial) {
            $keys[] = new Key('', KeyKind::Unique, [$position]);
        }

        return $keys;
    }

    /**
     * Builds a key a table element declares.
     *
     * @param list<ColumnDefinition> $columns
     *
     * @throws \MySqlMemory\Error\SqlError When a key part names no column
     */
    public function key(IndexDefinition $index, array $columns): Key
    {
        $positions = [];
        $prefixes = [];
        $descending = [];
        foreach ($index->parts as $part) {
            if (!$part instanceof ColumnPart) {
                throw StatementError::NotSupportedYet->error('functional key parts');
            }
            $position = null;
            foreach ($columns as $candidate => $column) {
                if (strcasecmp($column->name, $part->column->value) === 0) {
                    $position = $candidate;
                }
            }
            if ($position === null) {
                throw SchemaError::KeyColumnMissing->error($part->column->value);
            }
            $positions[] = $position;
            $prefixes[] = $part->length === null ? null : (int) $part->length->text;
            $descending[] = $part->direction === Direction::Descending;
        }
        $kind = match ($index->kind) {
            IndexKind::Primary => KeyKind::Primary,
            IndexKind::Unique => KeyKind::Unique,
            IndexKind::FullText => KeyKind::FullText,
            IndexKind::Spatial => KeyKind::Spatial,
            IndexKind::Index => KeyKind::Index,
        };

        return new Key($kind === KeyKind::Primary ? 'PRIMARY' : ($index->name?->column->value ?? $index->constraint?->name?->column->value ?? ''), $kind, $positions, $prefixes, $descending);
    }

    /**
     * Names the keys declared without a name after their first column, as the server names them.
     *
     * @param list<Key> $keys
     * @param list<ColumnDefinition> $columns
     * @return list<Key>
     */
    public function named(array $keys, array $columns): array
    {
        $used = [];
        foreach ($keys as $key) {
            if ($key->name !== '') {
                if (isset($used[strtolower($key->name)]) && $key->kind !== KeyKind::Primary) {
                    throw SchemaError::DuplicateKeyName->error($key->name);
                }
                $used[strtolower($key->name)] = true;
            }
        }
        $named = [];
        foreach ($keys as $key) {
            if ($key->name === '') {
                $base = $columns[$key->columns[0]]->name;
                $name = $base;
                for ($suffix = 2; isset($used[strtolower($name)]); $suffix++) {
                    $name = $base . '_' . $suffix;
                }
                $used[strtolower($name)] = true;
                $key = new Key($name, $key->kind, $key->columns, $key->prefixes, $key->descending, $key->generated);
            }
            $named[] = $key;
        }

        return $named;
    }

    /**
     * Checks the rules a whole definition must keep: a visible column, one AUTO_INCREMENT column that leads a key, one primary key.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/invisible-columns.html.
     *
     * @param list<ColumnDefinition> $columns
     * @param list<Key> $keys
     *
     * @throws \MySqlMemory\Error\SqlError When a rule is broken
     */
    public function check(array $columns, array $keys): void
    {
        if (array_filter($columns, static fn (ColumnDefinition $column): bool => !$column->invisible) === []) {
            throw SchemaError::NoVisibleColumn->error();
        }
        $automatic = array_keys(array_filter($columns, static fn (ColumnDefinition $column): bool => $column->autoIncrement));
        if (count($automatic) > 1) {
            throw SchemaError::WrongAutoKey->error();
        }
        if (count($automatic) === 1) {
            $leading = false;
            foreach ($keys as $key) {
                $leading = $leading || ($key->columns[0] === $automatic[0] && $key->kind !== KeyKind::FullText);
            }
            if (!$leading) {
                throw SchemaError::WrongAutoKey->error();
            }
        }
        if (count(array_filter($keys, static fn (Key $key): bool => $key->kind === KeyKind::Primary)) > 1) {
            throw SchemaError::MultiplePrimaryKey->error();
        }
    }
}
