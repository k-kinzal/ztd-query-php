<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

/**
 * The function MySQL 5.x SET PASSWORD writes around a cleartext password: PASSWORD() or OLD_PASSWORD().
 *
 * In 5.6 a bare string is the hash to store and `PASSWORD('…')` hashes a
 * cleartext password; 5.7 reads a bare string as cleartext and keeps
 * `PASSWORD('…')` as a deprecated equivalent.
 * Source: https://dev.mysql.com/doc/refman/5.6/en/set-password.html,
 * https://dev.mysql.com/doc/refman/5.7/en/set-password.html.
 *
 * @visibility public
 * @example Reading the keyword of a function
 *     \SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction::OldPassword->value // => 'OLD_PASSWORD'
 */
enum PasswordFunction: string
{
    case Password = 'PASSWORD';
    case OldPassword = 'OLD_PASSWORD';
}
