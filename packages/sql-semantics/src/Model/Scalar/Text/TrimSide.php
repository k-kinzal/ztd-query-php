<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Text;

/**
 * The end of a string from which TRIM removes characters, spelled as its SQL keyword.
 * @visibility public
 * @example Reading the keyword of a leading trim
 *     \SqlSemantics\Model\Scalar\Text\TrimSide::Leading->value // => 'LEADING'
 */
enum TrimSide: string
{
    case Both = 'BOTH';
    case Leading = 'LEADING';
    case Trailing = 'TRAILING';
}
