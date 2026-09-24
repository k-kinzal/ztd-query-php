<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Administration;

/**
 * The server TLS context that ALTER INSTANCE RELOAD TLS reconfigures.
 * @visibility public
 * @example Reading the channel name
 *     \SqlSemantics\Model\Configuration\Administration\TlsChannel::Admin->value // => 'mysql_admin'
 */
enum TlsChannel: string
{
    case Main = 'mysql_main';
    case Admin = 'mysql_admin';
}
