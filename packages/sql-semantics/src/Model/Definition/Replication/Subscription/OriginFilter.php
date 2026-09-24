<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Subscription;

/**
 * Which changes a subscription requests by their replication origin.
 * @visibility public
 * @example Reading the origin filter of a subscription
 *     (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION sub SET (origin = 'NONE')");
 *     $statement->options->origin // => \SqlSemantics\Model\Definition\Replication\Subscription\OriginFilter::None
 */
enum OriginFilter: string
{
    case None = 'none';
    case Any = 'any';
}
