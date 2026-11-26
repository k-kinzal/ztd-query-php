<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

/**
 * The unit a window frame is measured in.
 *
 * Source: https://sqlite.org/windowfunctions.html#frame_specifications.
 *
 * @visibility SqlSemantics
 */
enum FrameUnit: string
{
    case Range = 'RANGE';
    case Rows = 'ROWS';
    case Groups = 'GROUPS';
}
