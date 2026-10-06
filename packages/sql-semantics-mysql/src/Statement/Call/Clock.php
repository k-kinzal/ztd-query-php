<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;

/**
 * The functions that read the current date or time of the statement or of the system clock.
 *
 * Each case holds the keyword the function is written with. CURRENT_DATE is
 * the keyword CURDATE, CURRENT_TIME is CURTIME, and CURRENT_TIMESTAMP,
 * LOCALTIME and LOCALTIMESTAMP are NOW to the lexer. NOW and its synonyms
 * read the time the statement started; SYSDATE reads the time it executes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_now.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Clock::Now->value // => 'NOW'
 */
enum Clock: string
{
    case Now = 'NOW';
    case CurrentTime = 'CURTIME';
    case SystemDate = 'SYSDATE';
    case UtcTime = 'UTC_TIME';
    case UtcTimestamp = 'UTC_TIMESTAMP';
    case CurrentDate = 'CURDATE';
    case UtcDate = 'UTC_DATE';

    /**
     * Tells whether the function takes a fractional seconds precision.
     */
    public function precise(): bool
    {
        return $this !== self::CurrentDate && $this !== self::UtcDate;
    }

    /**
     * Answers the keyword that writes the function without parentheses, or null when it is only written with them.
     *
     * NOW, CURTIME, CURDATE and SYSDATE are keywords only before an opening
     * parenthesis (`SYM_FN` in sql/lex.h); without one the function is
     * written CURRENT_TIMESTAMP, CURRENT_TIME or CURRENT_DATE, and SYSDATE
     * cannot be.
     *
     * @example Reading the keyword NOW is written with without parentheses
     *     \SqlSemantics\Platform\MySql\Statement\Call\Clock::Now->bare() // => 'CURRENT_TIMESTAMP'
     */
    public function bare(): ?string
    {
        return match ($this) {
            self::Now => 'CURRENT_TIMESTAMP',
            self::CurrentTime => 'CURRENT_TIME',
            self::CurrentDate => 'CURRENT_DATE',
            self::SystemDate => null,
            self::UtcTime, self::UtcTimestamp, self::UtcDate => $this->value,
        };
    }

    /**
     * Answers the class of the type the function returns.
     */
    public function result(): TypeClass
    {
        return match ($this) {
            self::Now, self::SystemDate, self::UtcTimestamp => TypeClass::DateTime,
            self::CurrentTime, self::UtcTime => TypeClass::Time,
            self::CurrentDate, self::UtcDate => TypeClass::Date,
        };
    }
}
