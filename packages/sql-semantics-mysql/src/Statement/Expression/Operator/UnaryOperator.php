<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Operator;

/**
 * The prefix operators of the simple_expr level: `+`, `-`, `~` and the tight logical negation `!`.
 *
 * Under HIGH_NOT_PRECEDENCE the keyword NOT is the tight negation and is
 * written `!`. Each case holds the written operator.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/operator-precedence.html,
 * https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_high_not_precedence.
 *
 * @visibility public
 * @example Reading a unary minus
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE -a < 0');
 *     $query->statement->where->left->operator // => \SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus
 */
enum UnaryOperator: string
{
    case Plus = '+';
    case Minus = '-';
    case Invert = '~';
    case Not = '!';
}
