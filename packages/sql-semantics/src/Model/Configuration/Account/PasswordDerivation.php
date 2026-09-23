<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Account;

/**
 * MySQL 5.6 password derivation requests; hashing is performed by the consumer.
 * @visibility public
 * @example Inspecting the account operation
 *     \SqlSemantics\Model\Configuration\Account\PasswordDerivation::Configured->value // => 'PASSWORD'
 */
enum PasswordDerivation: string
{
    case Configured = 'PASSWORD';
    case Pre41 = 'OLD_PASSWORD';
}
