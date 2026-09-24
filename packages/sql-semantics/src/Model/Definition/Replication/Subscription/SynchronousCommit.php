<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Subscription;

/**
 * The synchronous_commit level a subscription's apply worker uses; true, yes and 1 read as on, and false, no and 0 as off.
 * @visibility public
 * @example Reading the commit level of a subscription
 *     (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION sub SET (synchronous_commit = 'Remote_Apply')");
 *     $statement->options->synchronousCommit // => \SqlSemantics\Model\Definition\Replication\Subscription\SynchronousCommit::RemoteApply
 */
enum SynchronousCommit: string
{
    case Local = 'local';
    case RemoteWrite = 'remote_write';
    case RemoteApply = 'remote_apply';
    case On = 'on';
    case Off = 'off';
}
