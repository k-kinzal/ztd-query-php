<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Account;

/**
 * Result fields produced by a random-password request.
 * @visibility public
 * @example Inspecting the account operation
 *     \SqlSemantics\Model\Configuration\Account\GeneratedPasswordField::Password->value // => 'generated password'
 */
enum GeneratedPasswordField: string
{
    case User = 'user';
    case Host = 'host';
    case Password = 'generated password';
    case AuthenticationFactor = 'auth_factor';
}
