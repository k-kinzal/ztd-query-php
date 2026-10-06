<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

/**
 * What an IDENTIFIED clause gives the authentication plugin.
 *
 * Mirrors the flags of the server's LEX_MFA: `BY 'password'` is a cleartext
 * password the plugin hashes, `BY RANDOM PASSWORD` asks the server to
 * generate one and return it, `AS 'string'` is the stored authentication
 * string as is, MySQL 5.x `BY PASSWORD 'hash'` is a hash in the format of the
 * default plugin, and a plugin named alone receives nothing.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-authentication,
 * https://dev.mysql.com/doc/refman/5.7/en/create-user.html.
 *
 * @visibility public
 * @example Naming the credential of a random password
 *     \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::RandomPassword->name // => 'RandomPassword'
 */
enum Credential
{
    case None;
    case Password;
    case RandomPassword;
    case Hash;
    case PasswordHash;

    /**
     * Tells whether the credential carries a string operand.
     */
    public function written(): bool
    {
        return match ($this) {
            self::Password, self::Hash, self::PasswordHash => true,
            self::None, self::RandomPassword => false,
        };
    }
}
