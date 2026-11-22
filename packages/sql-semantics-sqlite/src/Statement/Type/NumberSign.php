<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Type;

/**
 * The sign written before a signed number.
 *
 * Source: https://sqlite.org/syntax/signed-number.html.
 *
 * @visibility SqlSemantics
 */
enum NumberSign: string
{
    case Plus = '+';
    case Minus = '-';
}
