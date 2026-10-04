<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A number that an account statement writes outside the range the server accepts for it.
 *
 * The server rejects the statement while parsing it: ER_WRONG_VALUE for a
 * PASSWORD EXPIRE INTERVAL outside 1 to 65535 days, a FAILED_LOGIN_ATTEMPTS
 * or PASSWORD_LOCK_TIME above 32767, and a factor number other than 2 or 3;
 * ER_ONLY_INTEGERS_ALLOWED for a decimal or floating number at a position
 * that takes an integer.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-password-management,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-multifactor.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange('DAY', '0', 'ER_WRONG_VALUE'))->message() // => 'The value 0 is not accepted for DAY (ER_WRONG_VALUE).'
 */
final class NumberOutOfRange implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $option What the number is written for
     * @param string $number The number as written
     * @param string $error The server error the statement fails with
     */
    public function __construct(public readonly string $option, public readonly string $number, public readonly string $error)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'The value ' . $this->number . ' is not accepted for ' . $this->option . ' (' . $this->error . ').';
    }
}
