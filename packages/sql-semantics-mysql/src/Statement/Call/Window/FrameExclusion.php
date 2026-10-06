<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

/**
 * The rows the EXCLUDE clause of a window frame removes.
 *
 * Each case holds the keywords written after EXCLUDE. The grammar accepts the
 * clause; the server does not support it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-frames.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameExclusion::NoOthers->value // => 'NO OTHERS'
 */
enum FrameExclusion: string
{
    case CurrentRow = 'CURRENT ROW';
    case Group = 'GROUP';
    case Ties = 'TIES';
    case NoOthers = 'NO OTHERS';
}
