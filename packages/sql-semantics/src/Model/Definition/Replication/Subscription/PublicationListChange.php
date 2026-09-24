<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Subscription;

/**
 * How ALTER SUBSCRIPTION changes the publications it subscribes to.
 * @visibility public
 * @example Reading the publication change of a subscription
 *     (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION sub ADD PUBLICATION extra WITH (refresh = false)');
 *     $statement->change // => \SqlSemantics\Model\Definition\Replication\Subscription\PublicationListChange::Add
 */
enum PublicationListChange: string
{
    case Set = 'SET';
    case Add = 'ADD';
    case Drop = 'DROP';
}
