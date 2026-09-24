<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Account;

/**
 * The account name the client supplied when connecting, as reported by USER(); the consumer resolves it from the session.
 * @visibility public
 * @example Inspecting the account symbol
 *     \SqlSemantics\Model\Configuration\Account\ClientAccount::Connected->value // => 'USER()'
 */
enum ClientAccount: string
{
    case Connected = 'USER()';
}
