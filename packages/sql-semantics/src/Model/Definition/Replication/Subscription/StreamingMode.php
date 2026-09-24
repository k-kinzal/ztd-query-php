<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Subscription;

/**
 * How a subscription applies large in-progress transactions.
 * @visibility public
 * @example Reading the streaming mode of a subscription
 *     (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION sub SET (streaming = parallel)");
 *     $statement->options->streaming // => \SqlSemantics\Model\Definition\Replication\Subscription\StreamingMode::Parallel
 */
enum StreamingMode: string
{
    case Off = 'off';
    case On = 'on';
    case Parallel = 'parallel';
}
