<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Kind;

/**
 * Whether and where the BINARY attribute accompanies the character set of a character type.
 *
 * Each case names a placement; the keyword itself is BINARY.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-binary-collations.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark::Trailing->value // => 'trailing'
 */
enum BinaryMark: string
{
    case Absent = 'absent';
    case Leading = 'leading';
    case Trailing = 'trailing';
}
