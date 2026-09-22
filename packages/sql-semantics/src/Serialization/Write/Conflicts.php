<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Write;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Parts;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Conflict;
use SqlSemantics\Model\Write\ConflictAction;
use SqlSemantics\Serialization\Expressions;

/**

 * Keeps the conflict target distinct from the selected action. @visibility SqlSemantics

 */
final class Conflicts
{
    /**
     * @throws InvalidStructure
     */
    public static function write(ConflictAction $conflict, Dialect $dialect): Tree
    {
        if ($dialect === Dialect::MySql && $conflict instanceof Conflict\DoUpdate) {
            return new Tree('conflict', [Build::keyword('ON DUPLICATE KEY UPDATE'), Assignments::write($conflict->assignments)]);
        }
        $target = $conflict->target;
        $destination = match (true) {
            $target instanceof Conflict\AnyConflict => new Tree('any', []),
            $target instanceof Conflict\ConstraintConflict => new Tree('constraint', [Build::keyword('ON CONSTRAINT'), Build::identifier([$target->name], $dialect)]),
            $target instanceof Conflict\IndexConflict => new Tree('index', [Build::parentheses(Build::separated(array_map(Expressions::write(...), $target->keys))), Parts::expressions('WHERE', $target->predicate === null ? [] : [$target->predicate])]),
            default => throw new InvalidStructure('Unclassified conflict target.'),
        };
        $action = match (true) {
            $conflict instanceof Conflict\DoNothing => Build::keyword('DO NOTHING'),
            $conflict instanceof Conflict\DoUpdate => new Tree('update', [Build::keyword('DO UPDATE SET'), Assignments::write($conflict->assignments), Parts::expressions('WHERE', $conflict->where === null ? [] : [$conflict->where])]),
            default => throw new InvalidStructure('Unclassified conflict action.'),
        };
        return new Tree('conflict', [Build::keyword('ON CONFLICT'), $destination, $action]);
    }
}
