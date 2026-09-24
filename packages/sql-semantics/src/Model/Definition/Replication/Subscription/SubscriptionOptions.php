<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Subscription;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The WITH options of a subscription command; a null option is not specified and keeps its default or current value.
 * @visibility public
 * @example Reading subscription options
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE SUBSCRIPTION sub CONNECTION 'host=primary' PUBLICATION pub WITH (slot_name = sub_slot, streaming, copy_data = off)");
 *     $statement->options->slotName // => 'sub_slot'
 *     $statement->options->streaming // => \SqlSemantics\Model\Definition\Replication\Subscription\StreamingMode::On
 *     $statement->options->copyData // => false
 *     new \SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionOptions(slotName: 'Bad-Slot') // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SubscriptionOptions
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly ?bool $connect = null,
        public readonly ?bool $enabled = null,
        public readonly ?bool $createSlot = null,
        public readonly string|NoSlot|null $slotName = null,
        public readonly ?bool $copyData = null,
        public readonly ?SynchronousCommit $synchronousCommit = null,
        public readonly ?bool $refresh = null,
        public readonly ?bool $binary = null,
        public readonly ?StreamingMode $streaming = null,
        public readonly ?bool $twoPhase = null,
        public readonly ?bool $disableOnError = null,
        public readonly ?bool $passwordRequired = null,
        public readonly ?bool $runAsOwner = null,
        public readonly ?bool $failover = null,
        public readonly ?OriginFilter $origin = null,
    ) {
        if (is_string($slotName) && preg_match('/^[a-z0-9_]{1,63}$/D', $slotName) !== 1) {
            throw new InvalidStructure('A replication slot name has 1 to 63 lower-case letters, digits, and underscores.');
        }
    }

    /**
     * The specified options in declaration order.
     * @return list<SubscriptionParameter>
     */
    public function parameters(): array
    {
        return array_values(array_filter(SubscriptionParameter::cases(), fn (SubscriptionParameter $parameter): bool => $this->value($parameter) !== null));
    }

    /**
     * The value of one option: a Boolean, a slot name, NONE, the spelling of a level, mode or filter, or null when not specified.
     */
    public function value(SubscriptionParameter $parameter): bool|string|NoSlot|null
    {
        return match ($parameter) {
            SubscriptionParameter::Connect => $this->connect,
            SubscriptionParameter::Enabled => $this->enabled,
            SubscriptionParameter::CreateSlot => $this->createSlot,
            SubscriptionParameter::SlotName => $this->slotName,
            SubscriptionParameter::CopyData => $this->copyData,
            SubscriptionParameter::SynchronousCommit => $this->synchronousCommit?->value,
            SubscriptionParameter::Refresh => $this->refresh,
            SubscriptionParameter::Binary => $this->binary,
            SubscriptionParameter::Streaming => $this->streaming?->value,
            SubscriptionParameter::TwoPhase => $this->twoPhase,
            SubscriptionParameter::DisableOnError => $this->disableOnError,
            SubscriptionParameter::PasswordRequired => $this->passwordRequired,
            SubscriptionParameter::RunAsOwner => $this->runAsOwner,
            SubscriptionParameter::Failover => $this->failover,
            SubscriptionParameter::Origin => $this->origin?->value,
        };
    }
}
