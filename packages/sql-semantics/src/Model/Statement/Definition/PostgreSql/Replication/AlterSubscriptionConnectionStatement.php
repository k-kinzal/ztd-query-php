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
 * Replaces the connection string of a subscription.
 * @visibility public
 * @example Reading a new connection string
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION sub CONNECTION 'host=standby'");
 *     $statement->connection // => 'host=standby'
 */
final class AlterSubscriptionConnectionStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly string $connection,
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
        return new self($origin, $this->name, $this->connection);
    }

    /**
     * Replaces the subscription name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->connection));
    }

    /**
     * Replaces the connection string, which is kept without being parsed.
     */
    public function withConnection(string $connection): self
    {
        return $this->changed(new self($this->origin, $this->name, $connection));
    }
}
