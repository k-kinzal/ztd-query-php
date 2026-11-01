<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Conversion;

/**
 * The two zone values accepted by CAST ... AT TIME ZONE ... AS DATETIME.
 *
 * Other aliases remain invalid even when installed in the zone catalog.
 * The named UTC case still requires the consumer's named-zone catalog.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
 *
 * @visibility public
 * @example Selecting the catalog-independent zero offset
 *     \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\TimeZone::ZeroOffset->value // => '+00:00'
 */
enum TimeZone: string
{
    case Utc = 'UTC';
    case ZeroOffset = '+00:00';
}
