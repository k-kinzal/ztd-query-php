<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

/**
 * The state of the XA transaction of a session.
 *
 * XA START makes the transaction ACTIVE and XA END makes it IDLE. XA PREPARE retains
 * PREPARED ownership unless xa_detach_on_prepare detaches the branch. Each case holds
 * the name the server reports the state by.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-states.html.
 *
 * @visibility MySqlMemory
 */
enum XaState: string
{
    case NonExisting = 'NON-EXISTING';
    case Active = 'ACTIVE';
    case Idle = 'IDLE';
    case Prepared = 'PREPARED';
}
