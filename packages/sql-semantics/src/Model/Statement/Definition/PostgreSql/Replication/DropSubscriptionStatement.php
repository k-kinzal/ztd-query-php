<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes a subscription and, while connected, its replication slot.
 * @visibility public
 * @example Reading a tolerant subscription removal
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP SUBSCRIPTION IF EXISTS sub CASCADE');
 *     $statement->ifExists // => true
 *     $statement->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Cascade
 */
final class DropSubscriptionStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly bool $ifExists = false,
        public readonly DropBehavior $behavior = DropBehavior::Default,
    ) {
        SubscriptionInvariant::identity($origin, $name);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the subscription name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->ifExists, $this->behavior));
    }

    /**
     * Chooses whether a missing subscription is tolerated.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $ifExists, $this->behavior));
    }

    /**
     * Replaces the dependency behavior.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->ifExists, $behavior));
    }
}
