<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\MySqlTable;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Column;
use SqlSemantics\Model\Definition\MySqlTable\Key;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Operator\UnaryExpression;
use SqlSemantics\Model\Scalar\Value\IntroducedLiteral;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\TemporalLiteral;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Serialization\Definition\Columns;
use SqlSemantics\Serialization\Definition\Constraints;
use SqlSemantics\Serialization\Definition\Indexes;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes MySQL column, index, and constraint alterations from their typed operands.
 * @visibility SqlSemantics
 */
final class ColumnChanges
{
    /**
     * Writes a column or key alteration, or returns null for another alteration.
     */
    public static function write(object $alteration): ?Tree
    {
        return match (true) {
            $alteration instanceof Column\AddColumn => new Tree('add-column', [Build::keyword('ADD COLUMN'), self::declaration($alteration->column, $alteration->constraints), self::position($alteration->position)]),
            $alteration instanceof Column\AddColumns => new Tree('add-columns', [Build::keyword('ADD COLUMN'), Build::parentheses(Build::separated([
                ...array_map(static fn (ColumnDefinition $column): Tree => Columns::write($column, Dialect::MySql), $alteration->columns),
                ...array_map(static fn (TableConstraint $constraint): Tree => Constraints::write($constraint, Dialect::MySql), $alteration->constraints),
                ...array_map(static fn ($index): Tree => self::index($index), $alteration->indexes),
            ]))]),
            $alteration instanceof Column\ChangeColumn => new Tree('change-column', [Build::keyword('CHANGE COLUMN'), self::name($alteration->column), self::declaration($alteration->definition, $alteration->constraints), self::position($alteration->position)]),
            $alteration instanceof Column\ModifyColumn => new Tree('modify-column', [Build::keyword('MODIFY COLUMN'), self::declaration($alteration->definition, $alteration->constraints), self::position($alteration->position)]),
            $alteration instanceof Column\DropColumn => new Tree('drop-column', [Build::keyword('DROP COLUMN'), self::name($alteration->column), Build::keyword($alteration->behavior->value)]),
            $alteration instanceof Column\ColumnDefaultAssignment => new Tree('set-default', [Build::keyword('ALTER COLUMN'), self::name($alteration->column), Build::keyword('SET DEFAULT'), self::default($alteration->default)]),
            $alteration instanceof Column\ColumnDefaultRemoval => new Tree('drop-default', [Build::keyword('ALTER COLUMN'), self::name($alteration->column), Build::keyword('DROP DEFAULT')]),
            $alteration instanceof Column\SetColumnVisibility => new Tree('column-visibility', [Build::keyword('ALTER COLUMN'), self::name($alteration->column), Build::keyword($alteration->visible ? 'SET VISIBLE' : 'SET INVISIBLE')]),
            $alteration instanceof Column\RenameColumn => new Tree('rename-column', [Build::keyword('RENAME COLUMN'), self::name($alteration->column), Build::keyword('TO'), self::name($alteration->newName)]),
            $alteration instanceof Key\AddIndex => new Tree('add-index', [Build::keyword('ADD'), self::index($alteration->index)]),
            $alteration instanceof Key\AddConstraint => new Tree('add-constraint', [Build::keyword('ADD'), Constraints::write($alteration->constraint, Dialect::MySql)]),
            $alteration instanceof Key\DropKey => new Tree('drop-key', [Build::keyword('DROP ' . $alteration->kind->value), self::name($alteration->name)]),
            $alteration instanceof Key\SetIndexVisibility => new Tree('index-visibility', [Build::keyword('ALTER INDEX'), self::name($alteration->index), Build::keyword($alteration->visible ? 'VISIBLE' : 'INVISIBLE')]),
            $alteration instanceof Key\SetConstraintEnforcement => new Tree('enforcement', [Build::keyword('ALTER ' . $alteration->kind->value), self::name($alteration->name), Build::keyword($alteration->enforced ? 'ENFORCED' : 'NOT ENFORCED')]),
            $alteration instanceof Key\RenameIndex => new Tree('rename-index', [Build::keyword('RENAME INDEX'), self::name($alteration->index), Build::keyword('TO'), self::name($alteration->newName)]),
            default => null,
        };
    }

    /**
     * Writes a column declaration followed by the constraints written on it.
     * @param list<TableConstraint> $constraints
     */
    public static function declaration(ColumnDefinition $column, array $constraints): Tree
    {
        return new Tree('column-declaration', [Columns::write($column, Dialect::MySql), ...array_map(static fn (TableConstraint $constraint): Tree => Constraints::column($constraint, Dialect::MySql), $constraints)]);
    }

    /**
     * Writes FIRST or AFTER name.
     */
    public static function position(Column\FirstColumn|Column\AfterColumn|null $position): Tree
    {
        return match (true) {
            $position === null => new Tree('position', []),
            $position instanceof Column\AfterColumn => new Tree('position', [Build::keyword('AFTER'), self::name($position->column)]),
            default => Build::keyword($position->value),
        };
    }

    /**
     * Writes a signed or introduced literal default as written and any other default in parentheses.
     */
    public static function default(Expression $default): Tree
    {
        if ($default instanceof UnaryExpression && $default->operand instanceof Literal && in_array($default->operator->value, ['-', '+'], true)) {
            return new Tree('signed-literal', [Build::keyword($default->operator->value), Expressions::write($default->operand)]);
        }
        $literal = $default instanceof Literal || $default instanceof IntroducedLiteral || $default instanceof TemporalLiteral;
        return $literal ? Expressions::write($default) : Build::parentheses(Expressions::write($default));
    }

    /**
     * Writes an index declaration with its type after the key list.
     */
    public static function index(\SqlSemantics\Schema\IndexDefinition $index): Tree
    {
        return new Tree('index', [Build::keyword(Indexes::kind($index) . 'INDEX'), ...($index->name === null ? [] : [self::name($index->name)]), Indexes::keys($index, Dialect::MySql), IndexCreations::method($index), Indexes::options($index->properties, Dialect::MySql)]);
    }

    /**
     * Writes one identifier.
     */
    public static function name(string $name): Tree
    {
        return Build::identifier([$name], Dialect::MySql);
    }
}
