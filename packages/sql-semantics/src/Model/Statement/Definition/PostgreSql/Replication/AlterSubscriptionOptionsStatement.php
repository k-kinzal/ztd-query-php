<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionInvariant;
use SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionOptions;
use SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionParameter;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes WITH options of a subscription; unspecified options keep their values.
 * @visibility public
 * @example Reading changed subscription options
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION sub SET (binary = true, origin = any)");
 *     $statement->options->binary // => true
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'ALTER SUBSCRIPTION "sub" SET (binary = true, origin = \'any\')'
 */
final class AlterSubscriptionOptionsStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly SubscriptionOptions $options,
    ) {
        SubscriptionInvariant::identity($origin, $name);
        if ($options->parameters() === []) {
            throw new InvalidStructure('ALTER SUBSCRIPTION SET changes at least one option.');
        }
        SubscriptionInvariant::options($options, [SubscriptionParameter::SlotName, SubscriptionParameter::SynchronousCommit, SubscriptionParameter::Binary, SubscriptionParameter::Streaming, SubscriptionParameter::DisableOnError, SubscriptionParameter::PasswordRequired, SubscriptionParameter::RunAsOwner, SubscriptionParameter::Failover, SubscriptionParameter::Origin]);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->options);
    }

    /**
     * Replaces the subscription name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->options));
    }

    /**
     * Replaces the changed options, at least one.
     */
    public function withOptions(SubscriptionOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options));
    }
}
