<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Model\Scalar\Text\Position;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes string operations from their named semantic operands.
 * @visibility SqlSemantics
 */
final class TextExpressions
{
    /**
     * Writes a substring search with the needle before the searched input.
     */
    public static function write(Position $value): Tree
    {
        return new Tree('position', [Build::keyword('POSITION'), Build::parentheses(new Tree('search', [Expressions::write($value->needle), Build::keyword('IN'), Expressions::write($value->haystack)]))]);
    }
}
