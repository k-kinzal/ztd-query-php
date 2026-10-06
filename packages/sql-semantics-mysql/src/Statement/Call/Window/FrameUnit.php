<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

/**
 * The unit a window frame counts in.
 *
 * Each case holds the keyword. The grammar accepts GROUPS; the server does
 * not support it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-frames.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Range->value // => 'RANGE'
 */
enum FrameUnit: string
{
    case Rows = 'ROWS';
    case Range = 'RANGE';
    case Groups = 'GROUPS';
}
