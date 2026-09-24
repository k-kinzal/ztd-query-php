<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Relation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Column;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Definition\Relation\Storage;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Definition\Columns;
use SqlSemantics\Serialization\Definition\Constraints;
use SqlSemantics\Serialization\Definition\Foreign\WrapperOptions;
use SqlSemantics\Serialization\Definition\Storage as StorageWriter;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\Nullability;

/**
 * Writes column additions, removals, and ALTER COLUMN changes.
 * @visibility SqlSemantics
 */
final class ColumnActions
{
    /**
     * Returns null for actions outside the column family.
     */
    public static function write(RelationAction $action): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        if ($action instanceof Column\AddColumn) {
            return self::add($action);
        }
        if ($action instanceof Column\DropColumn) {
            return new Tree('drop-column', [Build::keyword('DROP COLUMN' . ($action->ifExists ? ' IF EXISTS' : '')), Build::identifier([$action->column], $dialect), Build::keyword($action->behavior->value)]);
        }
        $change = match (true) {
            $action instanceof Column\ColumnDefaultChange => $action->default === null ? [Build::keyword('DROP DEFAULT')] : [Build::keyword('SET DEFAULT'), Expressions::write($action->default)],
            $action instanceof Column\SetColumnNullability => [Build::keyword($action->nullability === Nullability::NotNull ? 'SET NOT NULL' : 'DROP NOT NULL')],
            $action instanceof Column\SetColumnExpression => [Build::keyword('SET EXPRESSION AS'), Build::parentheses(Expressions::write($action->expression))],
            $action instanceof Column\DropColumnExpression => [Build::keyword('DROP EXPRESSION' . ($action->ifExists ? ' IF EXISTS' : ''))],
            $action instanceof Column\SetColumnStatistics => [Build::keyword('SET STATISTICS ' . ($action->target === -1 ? 'DEFAULT' : (string) $action->target))],
            $action instanceof Column\SetColumnStorage => [Build::keyword('SET STORAGE ' . $action->storage->value)],
            $action instanceof Column\SetColumnCompression => [Build::keyword('SET COMPRESSION ' . $action->compression->value)],
            $action instanceof Column\ColumnTypeChange => self::type($action),
            default => null,
        };
        if ($change === null) {
            return self::options($action);
        }
        if ($action instanceof Column\SetColumnStatistics) {
            return self::alter(is_int($action->column) ? Build::keyword((string) $action->column) : Build::identifier([$action->column], $dialect), $change);
        }
        return self::alter(Build::identifier([$action->column], $dialect), $change);
    }

    /**
     * Prefixes a column change with ALTER COLUMN and the addressed column.
     * @param list<Tree> $change
     */
    public static function alter(Tree $column, array $change): Tree
    {
        return new Tree('alter-column', [Build::keyword('ALTER COLUMN'), $column, ...$change]);
    }

    /**
     * Writes column storage options, foreign options, and identity changes.
     */
    public static function options(RelationAction $action): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        $change = match (true) {
            $action instanceof Storage\SetColumnOptions => [Build::keyword('SET'), Build::parentheses(StorageWriter::parameters($action->parameters, $dialect))],
            $action instanceof Storage\ResetColumnOptions => [Build::keyword('RESET'), Build::parentheses(Build::separated(array_map(static fn ($name): Tree => Build::identifier($name->parts, $dialect), $action->names)))],
            $action instanceof Storage\SetColumnForeignOptions => [Build::keyword('OPTIONS'), Build::parentheses(Build::separated(array_map(WrapperOptions::change(...), $action->changes)))],
            $action instanceof Identity\AddColumnIdentity, $action instanceof Identity\SetColumnIdentity, $action instanceof Identity\DropColumnIdentity => IdentityActions::write($action),
            default => null,
        };
        return $change === null ? null : self::alter(Build::identifier([$action->column], $dialect), $change);
    }

    /**
     * Wrapper options precede the column constraints, as the grammar requires.
     */
    public static function add(Column\AddColumn $action): Tree
    {
        $dialect = Dialect::PostgreSql;
        $column = Columns::write($action->column, $dialect)->children;
        $options = $action->options === [] ? [] : [Build::keyword('OPTIONS'), Build::parentheses(Build::separated(array_map(WrapperOptions::option(...), $action->options)))];
        return new Tree('add-column', [Build::keyword('ADD COLUMN' . ($action->ifNotExists ? ' IF NOT EXISTS' : '')), ...array_slice($column, 0, 3), ...$options, ...array_slice($column, 3), ...array_map(static fn ($constraint): Tree => Constraints::column($constraint, $dialect), $action->constraints)]);
    }

    /**
     * @return list<Tree>
     */
    public static function type(Column\ColumnTypeChange $action): array
    {
        $dialect = Dialect::PostgreSql;
        return [
            Build::keyword('TYPE'), TypeDeclaration::write($action->type),
            ...($action->collation === null ? [] : [Build::keyword('COLLATE'), Build::identifier($action->collation->parts, $dialect)]),
            ...($action->using === null ? [] : [Build::keyword('USING'), Expressions::write($action->using)]),
        ];
    }
}
