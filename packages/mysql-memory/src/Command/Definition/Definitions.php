<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Dictionary\ColumnDefault;
use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Clock;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Storage\Store;
use MySqlMemory\Typing\Declared;
use MySqlMemory\Typing\Domain;
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
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;
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
     * Builds the definition of a table.
     *
     * @throws \MySqlMemory\Error\SqlError When the definition is invalid
     */
    public function table(CreateTable $create, Table $declaration, string $schema): TableDefinition
    {
        $collation = $this->collation($create);
        $engine = 'InnoDB';
        foreach ($create->options as $option) {
            if ($option instanceof EngineOption) {
                $engine = $option->engine->value;
            }
        }
        $elements = array_values(array_filter($create->elements, static fn ($element): bool => $element instanceof ColumnElement));
        $scope = new Scope();
        $declared = new Declared($collation, $this->planner->settings->release());
        $columns = [];
        $keys = [];
        foreach ($elements as $position => $element) {
            $columns[] = $this->column($element, $declaration->columns[$position] ?? null, $declared, $scope, $create);
            foreach ($this->inlineKeys($element, $position) as $key) {
                $keys[] = $key;
            }
        }
        foreach ($create->elements as $element) {
            if ($element instanceof IndexDefinition) {
                $keys[] = $this->key($element, $columns);
            }
        }
        $keys = $this->named($keys, $columns);
        $this->check($columns, $keys);
        usort($keys, static fn (Key $left, Key $right): int => ($right->kind === KeyKind::Primary) <=> ($left->kind === KeyKind::Primary));

        return new TableDefinition($schema, $create->name->name->value, $columns, $keys, $declaration, $engine, $collation->name, $create->temporaryWords > 0);
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
        $domain = $resolved instanceof Resolved ? Domain::of($resolved, true) : $declared->domain($specification->dataType(), $collation);
        $nullable = $declaration === null ? true : $declaration->nullability !== Nullability::NotNull;
        $serial = $specification->dataType() instanceof Elementary && $specification->dataType()->kind === ElementaryKind::Serial;
        $domain = $domain->withNullable($nullable && !$serial);
        $keywords = array_map(static fn ($attribute) => $attribute->keyword, array_values(array_filter($attributes, static fn ($attribute): bool => $attribute instanceof KeywordAttribute)));
        $name = $element->name->column->value;
        $column = new ColumnDefinition($name, $domain, ColumnDefault::none(), $serial || in_array(ColumnKeyword::AutoIncrement, $keywords, true), false, null, in_array(ColumnKeyword::Invisible, $keywords, true), $declaration, $comment);

        return new ColumnDefinition($name, $domain, $this->default($attributes, $column, $create), $column->autoIncrement, $this->onUpdate($attributes), null, $column->invisible, $declaration, $comment);
    }

    /**
     * Answers the default of a column from its attributes.
     *
     * @param list<object> $attributes
     */
    public function default(array $attributes, ColumnDefinition $column, CreateTable $create): ColumnDefault
    {
        foreach ($attributes as $attribute) {
            if ($attribute instanceof DefaultLiteral || $attribute instanceof DefaultExpression) {
                $value = $attribute instanceof DefaultLiteral ? $attribute->value : $attribute->expression;
                if (in_array($column->domain->field, [Field::Blob, Field::Json], true) && $attribute instanceof DefaultLiteral) {
                    throw ErrorCode::BlobCantHaveDefault->error($column->name);
                }
                $evaluable = $this->planner->compiler->compile($value, new Scope());
                if ($evaluable instanceof Clock) {
                    return new ColumnDefault(true, null, $evaluable, true, 'CURRENT_TIMESTAMP');
                }
                if ($attribute instanceof DefaultExpression) {
                    return new ColumnDefault(true, null, $evaluable, false, null);
                }
                $frame = new Frame($this->planner->compiler->connection->context);
                $raw = $evaluable->evaluate($frame);
                if ($raw === null && !$column->nullable()) {
                    throw ErrorCode::InvalidDefault->error($column->name);
                }
                $context = $frame->context;
                $strict = $context->strict;
                $context->strict = true;
                try {
                    $stored = (new Store($context))->value($raw, $evaluable->domain(), $column);
                } catch (\MySqlMemory\Error\SqlError $error) {
                    throw new \MySqlMemory\Error\SqlError(ErrorCode::InvalidDefault, ErrorCode::InvalidDefault->message($column->name), $error);
                } finally {
                    $context->strict = $strict;
                }

                return ColumnDefault::constant($stored, $raw === null ? null : (string) $stored);
            }
        }

        return $column->nullable() && !$column->autoIncrement ? ColumnDefault::constant(null, null) : ColumnDefault::none();
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
        foreach ($index->parts as $part) {
            if (!$part instanceof ColumnPart) {
                throw ErrorCode::NotSupportedYet->error('functional key parts');
            }
            $position = null;
            foreach ($columns as $candidate => $column) {
                if (strcasecmp($column->name, $part->column->value) === 0) {
                    $position = $candidate;
                }
            }
            if ($position === null) {
                throw ErrorCode::KeyColumnMissing->error($part->column->value);
            }
            $positions[] = $position;
            $prefixes[] = $part->length === null ? null : (int) $part->length->text;
        }
        $kind = match ($index->kind) {
            IndexKind::Primary => KeyKind::Primary,
            IndexKind::Unique => KeyKind::Unique,
            IndexKind::FullText => KeyKind::FullText,
            IndexKind::Spatial => KeyKind::Spatial,
            IndexKind::Index => KeyKind::Index,
        };

        return new Key($kind === KeyKind::Primary ? 'PRIMARY' : ($index->name?->column->value ?? $index->constraint?->name?->column->value ?? ''), $kind, $positions, $prefixes);
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
                    throw ErrorCode::DuplicateKeyName->error($key->name);
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
                $key = new Key($name, $key->kind, $key->columns, $key->prefixes);
            }
            $named[] = $key;
        }

        return $named;
    }

    /**
     * Checks the rules a whole definition must keep.
     *
     * @param list<ColumnDefinition> $columns
     * @param list<Key> $keys
     *
     * @throws \MySqlMemory\Error\SqlError When a rule is broken
     */
    public function check(array $columns, array $keys): void
    {
        $automatic = array_keys(array_filter($columns, static fn (ColumnDefinition $column): bool => $column->autoIncrement));
        if (count($automatic) > 1) {
            throw ErrorCode::WrongAutoKey->error();
        }
        if (count($automatic) === 1) {
            $leading = false;
            foreach ($keys as $key) {
                $leading = $leading || ($key->columns[0] === $automatic[0] && $key->kind !== KeyKind::FullText);
            }
            if (!$leading) {
                throw ErrorCode::WrongAutoKey->error();
            }
        }
        if (count(array_filter($keys, static fn (Key $key): bool => $key->kind === KeyKind::Primary)) > 1) {
            throw ErrorCode::MultiplePrimaryKey->error();
        }
    }
}
