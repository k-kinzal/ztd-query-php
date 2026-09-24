<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Procedural;

use SqlSemantics\Model\Configuration\Condition\SignalAssignment;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Procedural\ResignalStatement;
use SqlSemantics\Model\Statement\Procedural\SignalStatement;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes SIGNAL and RESIGNAL from the SQLSTATE and condition item values.
 * @visibility SqlSemantics
 */
final class Conditions
{
    /**
     * Omits the optional VALUE noise word and an empty SET list.
     */
    public static function write(SignalStatement|ResignalStatement $statement): Tree
    {
        $condition = $statement->condition === null ? [] : [self::state($statement->condition)];
        $assignments = $statement->assignments === [] ? [] : [Build::keyword('SET'), Build::separated(array_map(static fn (SignalAssignment $assignment): Tree => new Tree('signal-item', [Build::keyword($assignment->item->value . ' ='), Expressions::write($assignment->value)]), $statement->assignments))];
        return new Tree('signal', [Build::keyword($statement->kind->value), ...$condition, ...$assignments]);
    }

    /**
     * Spells SQLSTATE with its code as a string literal.
     */
    public static function state(SqlState $state): Tree
    {
        return new Tree('sqlstate', [Build::keyword('SQLSTATE'), Requests::text($state->code)]);
    }
}
