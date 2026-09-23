<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Account;

/**
 * The authenticated account, resolved by the consumer from the execution context.
 * @visibility public
 * @example Inspecting the account operation
 *     \SqlSemantics\Model\Configuration\Account\CurrentAccount::Authenticated->value // => 'CURRENT_USER'
 */
enum CurrentAccount: string
{
    case Authenticated = 'CURRENT_USER';
}
