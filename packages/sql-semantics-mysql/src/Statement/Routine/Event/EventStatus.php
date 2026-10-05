<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Event;

/**
 * Whether an event is active: ENABLE, DISABLE, or disabled on replicas.
 *
 * DISABLE ON SLAVE and DISABLE ON REPLICA mean the same; they are separate
 * cases because the releases accept different spellings (the grammar reads
 * SLAVE in every release and REPLICA from MySQL 8.2), so the written keyword
 * is kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html.
 *
 * @visibility public
 * @example Listing the cases
 *     count(\SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus::cases()) // => 4
 */
enum EventStatus
{
    case Enable;
    case Disable;
    case DisableOnSlave;
    case DisableOnReplica;
}
