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
 * Fetches the current table list of a subscription's publications.
 * @visibility public
 * @example Reading a refresh without initial copy
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION sub REFRESH PUBLICATION WITH (copy_data = false)');
 *     $statement->options->copyData // => false
 */
final class RefreshSubscriptionStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly SubscriptionOptions $options = new SubscriptionOptions(),
    ) {
        SubscriptionInvariant::identity($origin, $name);
        SubscriptionInvariant::options($options, [SubscriptionParameter::CopyData]);
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
     * Replaces the WITH options, which must suit this command.
     */
    public function withOptions(SubscriptionOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options));
    }
}
