<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

/**
 * An additional authentication factor: the second or third factor.
 *
 * These are the only accepted factor operands, including their spelling:
 * 02 and 003 are rejected instead of denoting the corresponding factor.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-multifactor.
 *
 * @visibility public
 * @example Reading an authentication factor
 *     \SqlSemantics\Platform\MySql\Statement\Account\User\Factor::Second->value // => '2'
 */
enum Factor: string
{
    case Second = '2';
    case Third = '3';
}
