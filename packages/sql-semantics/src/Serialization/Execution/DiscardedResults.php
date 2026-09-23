<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Execution;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Execution\DoExpressionsStatement;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes scalar evaluation requests in their original operand order.
 * @visibility SqlSemantics
 */
final class DiscardedResults
{
    /**
     * Emits each expression from its semantic operands without evaluating it.
     */
    public static function write(DoExpressionsStatement $statement): Tree
    {
        return new Tree('discarded_results', [Build::keyword('DO'), Build::separated(array_map(Expressions::write(...), $statement->expressions))]);
    }
}
