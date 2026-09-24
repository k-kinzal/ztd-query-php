<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

/**
 * A replica thread that START REPLICA or STOP REPLICA controls; RELAY_THREAD is another spelling of IO_THREAD.
 * @visibility public
 * @example Reading the keyword
 *     \SqlSemantics\Model\Configuration\Replication\ReplicaThread::Receiver->value // => 'IO_THREAD'
 */
enum ReplicaThread: string
{
    case Receiver = 'IO_THREAD';
    case Applier = 'SQL_THREAD';
}
