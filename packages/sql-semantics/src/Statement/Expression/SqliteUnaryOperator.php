<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

/**
 * SQLite's scalar prefix operations, including the affinity-removing unary plus.
 * @visibility public
 * @example Selecting a prefix operation
 *     \SqlSemantics\Statement\Expression\SqliteUnaryOperator::BitwiseNot->value // => '~'
 */
enum SqliteUnaryOperator: string
{
    case Plus = '+';
    case Negate = '-';
    case Not = 'NOT';
    case BitwiseNot = '~';
}
