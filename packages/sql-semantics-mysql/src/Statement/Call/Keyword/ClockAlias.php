<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Keyword;

use SqlSemantics\Platform\MySql\Statement\Call\Clock;

/**
 * A clock keyword retaining its written synonym separately from the clock it reads.
 *
 * These alternatives accept both bare and parenthesized calls. The canonical
 * CURDATE, CURTIME and NOW names require parentheses.
 *
 * @visibility public
 * @example Reading a timestamp synonym
 *     \SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias::LocalTimestamp->clock() // => \SqlSemantics\Platform\MySql\Statement\Call\Clock::Now
 */
enum ClockAlias: string
{
    case CurrentDate = 'CURRENT_DATE';
    case CurrentTime = 'CURRENT_TIME';
    case CurrentTimestamp = 'CURRENT_TIMESTAMP';
    case LocalTime = 'LOCALTIME';
    case LocalTimestamp = 'LOCALTIMESTAMP';

    /**
     * Answers the clock operation, independently of its keyword.
     */
    public function clock(): Clock
    {
        return match ($this) {
            self::CurrentDate => Clock::CurrentDate,
            self::CurrentTime => Clock::CurrentTime,
            self::CurrentTimestamp, self::LocalTime, self::LocalTimestamp => Clock::Now,
        };
    }
}
