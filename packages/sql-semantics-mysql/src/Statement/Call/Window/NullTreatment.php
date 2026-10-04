<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

/**
 * Whether a value window function skips NULL values, as written after its arguments.
 *
 * Each case holds the keywords. RESPECT NULLS is the default; the server
 * does not support IGNORE NULLS.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-descriptions.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment::Ignore->value // => 'IGNORE NULLS'
 */
enum NullTreatment: string
{
    case Respect = 'RESPECT NULLS';
    case Ignore = 'IGNORE NULLS';
}
