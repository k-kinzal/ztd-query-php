<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

/**
 * The notation of a bit-string constant: binary digits (`B'...'`) or hexadecimal digits (`X'...'`).
 *
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-BIT-STRINGS.
 *
 * @visibility public
 * @example Reading the prefix of the hexadecimal notation
 *     \SqlSemantics\Platform\PostgreSql\Statement\Literal\BitStringRadix::Hexadecimal->value // => 'x'
 */
enum BitStringRadix: string
{
    case Binary = 'b';
    case Hexadecimal = 'x';
}
