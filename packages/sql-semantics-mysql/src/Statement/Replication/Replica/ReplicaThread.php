<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Replica;

/**
 * The replication threads START and STOP REPLICA can name: the server's REPLICA_SQL and REPLICA_IO flags.
 *
 * Each case holds the keyword it is written with; the lexer reads
 * RELAY_THREAD as a synonym of IO_THREAD.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/start-replica.html.
 *
 * @visibility public
 * @example Reading the keyword of a thread
 *     \SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread::Receiver->value // => 'IO_THREAD'
 */
enum ReplicaThread: string
{
    case Applier = 'SQL_THREAD';
    case Receiver = 'IO_THREAD';
}
