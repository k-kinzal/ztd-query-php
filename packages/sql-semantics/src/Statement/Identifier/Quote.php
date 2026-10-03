<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Identifier;

/**
 * The quoting convention of a decoded identifier.
 * @example Describing the operation
 *     $quote = \SqlSemantics\Statement\Identifier\Quote::Double;
 *     $quote->value // => '"'
 * @visibility public
 */
enum Quote: string
{
    case None = '';
    case Double = '"';
    case Backtick = '`';
    case Bracket = '[';
    case Single = "'";
}
