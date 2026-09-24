<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Procedural;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Cursor\Handler as Statement;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Inspection\SessionInspections;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes HANDLER operations from the handler, the read position, the condition and the window.
 * @visibility SqlSemantics
 */
final class Handlers
{
    /**
     * Names an open handler by its last name part, the only form READ and CLOSE accept.
     */
    public static function write(Statement\OpenHandlerStatement|Statement\CloseHandlerStatement|Statement\ReadHandlerStatement|Statement\ReadHandlerIndexStatement|Statement\ReadHandlerKeyStatement $statement): Tree
    {
        if ($statement instanceof Statement\OpenHandlerStatement) {
            $alias = $statement->table->alias === null ? [] : [Build::keyword('AS'), Build::identifier([$statement->table->alias], Dialect::MySql)];
            return new Tree('handler', [Build::keyword('HANDLER'), Relations::target($statement->table, Dialect::MySql), Build::keyword('OPEN'), ...$alias]);
        }
        $parts = $statement->handler->name->parts;
        $handler = [Build::keyword('HANDLER'), Build::identifier([$parts[count($parts) - 1]], Dialect::MySql)];
        if ($statement instanceof Statement\CloseHandlerStatement) {
            return new Tree('handler', [...$handler, Build::keyword('CLOSE')]);
        }
        $position = match (true) {
            $statement instanceof Statement\ReadHandlerStatement => [Build::keyword($statement->scan->value)],
            $statement instanceof Statement\ReadHandlerIndexStatement => [Build::identifier([$statement->index], Dialect::MySql), Build::keyword($statement->step->value)],
            $statement instanceof Statement\ReadHandlerKeyStatement => [Build::identifier([$statement->index], Dialect::MySql), Build::keyword($statement->comparison->value), Build::parentheses(Build::separated(array_map(Expressions::write(...), $statement->key)))],
        };
        $where = $statement->where === null ? [] : [Build::keyword('WHERE'), Expressions::write($statement->where)];
        return new Tree('handler', [...$handler, Build::keyword('READ'), ...$position, ...$where, ...SessionInspections::window($statement->limit)]);
    }
}
