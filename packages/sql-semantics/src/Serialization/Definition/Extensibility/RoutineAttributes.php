<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Extensibility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Option;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\Routine\AlterRoutineStatement;
use SqlSemantics\Serialization\Definition\Routines;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Settings;

/**
 * Writes PostgreSQL routine attributes and the ALTER statement that changes them.
 * @visibility SqlSemantics
 */
final class RoutineAttributes
{
    /**
     * Writes ALTER FUNCTION, PROCEDURE, or ROUTINE; returns null for other statements.
     */
    public static function alteration(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof AlterRoutineStatement) {
            return null;
        }
        return new Tree('alter-routine', [Build::keyword('ALTER ' . $statement->routine->value), Routines::routine($statement->target), ...array_map(self::write(...), $statement->changes)]);
    }

    /**
     * Writes one attribute with its canonical keywords.
     */
    public static function write(Option\RoutineOption|RoutineSecurity $option): Tree
    {
        return match (true) {
            $option instanceof Option\Volatility, $option instanceof Option\NullInputBehavior, $option instanceof Option\LeakproofBehavior => Build::keyword($option->value),
            $option instanceof RoutineSecurity => Build::keyword('SECURITY ' . $option->value),
            $option instanceof Option\ParallelSafety => Build::keyword('PARALLEL ' . $option->value),
            $option instanceof Option\ExecutionCost => new Tree('routine-attribute', [Build::keyword('COST'), Expressions::write($option->cost)]),
            $option instanceof Option\ResultRows => new Tree('routine-attribute', [Build::keyword('ROWS'), Expressions::write($option->rows)]),
            $option instanceof Option\SupportFunction => new Tree('routine-attribute', [Build::keyword('SUPPORT'), Build::identifier($option->function->parts, Dialect::PostgreSql)]),
            $option instanceof Option\RoutineSetting => new Tree('routine-attribute', [Build::keyword('SET'), Settings::assignment($option->setting, Dialect::PostgreSql)]),
            $option instanceof Option\RoutineReset => new Tree('routine-attribute', [Build::keyword('RESET'), $option->setting === null ? Build::keyword('ALL') : Build::identifier($option->setting->name, Dialect::PostgreSql)]),
            default => new Tree('routine-attribute', []),
        };
    }
}
