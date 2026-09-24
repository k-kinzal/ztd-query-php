<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Subscription;

/**
 * The NONE slot name, which dissociates a subscription from any replication slot.
 * @visibility public
 * @example Reading a subscription without a slot
 *     (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION sub SET (slot_name = NONE)");
 *     $statement->options->slotName // => \SqlSemantics\Model\Definition\Replication\Subscription\NoSlot::None
 */
enum NoSlot: string
{
    case None = 'NONE';
}
