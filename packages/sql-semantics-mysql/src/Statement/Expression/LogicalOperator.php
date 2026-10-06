<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;

/**
 * The binary logical operators of MySQL.
 *
 * `&&` is a synonym of AND and, unless PIPES_AS_CONCAT is set, `||` is a
 * synonym of OR; both are written in their keyword form. Each case holds
 * the keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/logical-operators.html.
 *
 * @visibility public
 * @example Reading the operator of a disjunction
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a || b');
 *     $query->statement->where->operator // => \SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator::Or
 */
enum LogicalOperator: string
{
    case Or = 'OR';
    case Xor = 'XOR';
    case And = 'AND';

    /**
     * Answers the binding level of the operator on the scale of MYSQL-PRECEDENCE-001.
     */
    public function level(): int
    {
        return match ($this) {
            self::Or => Precedence::DISJUNCTION,
            self::Xor => Precedence::EXCLUSION,
            self::And => Precedence::CONJUNCTION,
        };
    }
}
