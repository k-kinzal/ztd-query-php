<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Enables or disables a subscription's replication.
 * @visibility public
 * @example Reading an enabled change
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION sub DISABLE');
 *     $statement->enabled // => false
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'ALTER SUBSCRIPTION "sub" DISABLE'
 */
final class AlterSubscriptionEnabledStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly bool $enabled,
    ) {
        SubscriptionInvariant::identity($origin, $name);
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
        return new self($origin, $this->name, $this->enabled);
    }

    /**
     * Replaces the subscription name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->enabled));
    }

    /**
     * Chooses whether the subscription is enabled.
     */
    public function withEnabled(bool $enabled): self
    {
        return $this->changed(new self($this->origin, $this->name, $enabled));
    }
}
