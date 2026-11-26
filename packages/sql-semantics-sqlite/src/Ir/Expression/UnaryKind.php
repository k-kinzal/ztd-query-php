<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Expression;

/**
 * The closed prefix operations of a SQLite scalar.
 *
 * @visibility public
 * @example Reading the signed bit complement token
 *     \SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryKind::Complement->value // => '~'
 */
enum UnaryKind: string
{
    case Plus = '+';
    case Minus = '-';
    case Not = 'NOT';
    case Complement = '~';
}
