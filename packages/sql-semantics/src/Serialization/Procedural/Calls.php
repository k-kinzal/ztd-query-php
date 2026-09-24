<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Procedural;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Procedural\CallStatement;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes CALL from the procedure name and argument expressions.
 * @visibility SqlSemantics
 */
final class Calls
{
    /**
     * Always spells the argument list, which is equivalent to omitting an empty one.
     */
    public static function write(CallStatement $statement): Tree
    {
        return new Tree('call', [Build::keyword('CALL'), Build::identifier($statement->procedure->parts, Dialect::MySql), Build::parentheses(Build::separated(array_map(Expressions::write(...), $statement->arguments)))]);
    }
}
