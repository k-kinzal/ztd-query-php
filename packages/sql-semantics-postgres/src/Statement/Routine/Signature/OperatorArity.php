<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature;

/**
 * How the operand types of an operator signature are written.
 *
 * `(left, right)` is a binary operator, `(NONE, right)` a prefix operator and
 * `(left, NONE)` a postfix operator, which the server no longer supports. A
 * single type without NONE is accepted by the grammar and rejected by the
 * server as a missing argument.
 * Source: https://www.postgresql.org/docs/17/sql-dropoperator.html.
 *
 * @visibility public
 * @example Counting the written types of a prefix operator
 *     \SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorArity::Prefix->types() // => 1
 */
enum OperatorArity
{
    case Binary;
    case Prefix;
    case Postfix;
    case Incomplete;

    /**
     * Answers the number of types written.
     */
    public function types(): int
    {
        return $this === self::Binary ? 2 : 1;
    }
}
