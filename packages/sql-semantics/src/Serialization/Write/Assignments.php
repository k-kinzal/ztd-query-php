<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Write;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Queries;

/**
 * Writes scalar, row and query assignments from their distinct mandatory inputs.
 *
 * @visibility SqlSemantics
 */
final class Assignments
{
    /**
     * @param non-empty-list<Assignment> $assignments
     */
    public static function write(array $assignments): Tree
    {
        return Build::separated(array_map(self::assignment(...), $assignments));
    }

    /**
     * Keeps one-element tuples distinct from scalar assignments.
     * @throws InvalidStructure
     */
    public static function assignment(Assignment $assignment): Tree
    {
        $target = $assignment instanceof Assignment\ScalarAssignment || $assignment instanceof Assignment\DefaultAssignment ? StoragePaths::write($assignment->target) : Build::parentheses(Build::separated(array_map(StoragePaths::write(...), $assignment->destinations())));
        $input = match (true) {
            $assignment instanceof Assignment\DefaultAssignment => Build::keyword('DEFAULT'),
            $assignment instanceof Assignment\ScalarAssignment => Expressions::write($assignment->value),
            $assignment instanceof Assignment\TupleRowAssignment => new Tree('write-row', [Build::keyword('ROW'), Build::parentheses(Build::separated(array_map(Inputs::write(...), $assignment->row->items)))]),
            $assignment instanceof Assignment\TupleQueryAssignment => Build::parentheses(Queries::write($assignment->query)),
            default => throw new InvalidStructure('Unclassified assignment input.'),
        };
        return new Tree('assignment', [$target, Build::keyword('='), $input]);
    }
}
