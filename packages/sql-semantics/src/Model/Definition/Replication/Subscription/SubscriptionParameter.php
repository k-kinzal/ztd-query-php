<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Subscription;

/**
 * A documented subscription option name.
 * @visibility public
 * @example Listing the options a subscription specifies
 *     (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE SUBSCRIPTION sub CONNECTION 'host=primary' PUBLICATION pub WITH (connect = false, binary)");
 *     $statement->options->parameters() // => [\SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionParameter::Connect, \SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionParameter::Binary]
 */
enum SubscriptionParameter: string
{
    case Connect = 'connect';
    case Enabled = 'enabled';
    case CreateSlot = 'create_slot';
    case SlotName = 'slot_name';
    case CopyData = 'copy_data';
    case SynchronousCommit = 'synchronous_commit';
    case Refresh = 'refresh';
    case Binary = 'binary';
    case Streaming = 'streaming';
    case TwoPhase = 'two_phase';
    case DisableOnError = 'disable_on_error';
    case PasswordRequired = 'password_required';
    case RunAsOwner = 'run_as_owner';
    case Failover = 'failover';
    case Origin = 'origin';
}
