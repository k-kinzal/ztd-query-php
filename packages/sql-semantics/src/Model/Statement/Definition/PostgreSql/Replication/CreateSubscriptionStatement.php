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
 * Creates a subscription to publications of another server, reached through a connection string that is not parsed.
 * @visibility public
 * @example Reading a subscription
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE SUBSCRIPTION sub CONNECTION 'host=primary dbname=app' PUBLICATION pub, audit WITH (enabled = false)");
 *     $statement->connection // => 'host=primary dbname=app'
 *     $statement->publications // => ['pub', 'audit']
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE SUBSCRIPTION "sub" CONNECTION \'host=primary dbname=app\' PUBLICATION "pub", "audit" WITH (enabled = false)'
 */
final class CreateSubscriptionStatement extends BoundStatement
{
    /**
     * @var non-empty-list<string> Validated distinct publication names in written order
     */
    public readonly array $publications;

    /**
     * @param list<string> $publications
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly string $connection,
        array $publications,
        public readonly SubscriptionOptions $options = new SubscriptionOptions(),
    ) {
        SubscriptionInvariant::identity($origin, $name);
        $this->publications = SubscriptionInvariant::publications($publications);
        SubscriptionInvariant::options($options, [SubscriptionParameter::Connect, SubscriptionParameter::Enabled, SubscriptionParameter::CreateSlot, SubscriptionParameter::SlotName, SubscriptionParameter::CopyData, SubscriptionParameter::SynchronousCommit, SubscriptionParameter::Binary, SubscriptionParameter::Streaming, SubscriptionParameter::TwoPhase, SubscriptionParameter::DisableOnError, SubscriptionParameter::PasswordRequired, SubscriptionParameter::RunAsOwner, SubscriptionParameter::Failover, SubscriptionParameter::Origin]);
        SubscriptionInvariant::creation($options);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->connection, $this->publications, $this->options);
    }

    /**
     * Replaces the subscription name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->connection, $this->publications, $this->options));
    }

    /**
     * Replaces the connection string, which is kept without being parsed.
     */
    public function withConnection(string $connection): self
    {
        return $this->changed(new self($this->origin, $this->name, $connection, $this->publications, $this->options));
    }

    /**
     * Replaces the publication names.
     * @param list<string> $publications
     */
    public function withPublications(array $publications): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->connection, $publications, $this->options));
    }

    /**
     * Replaces the WITH options, which must suit this command.
     */
    public function withOptions(SubscriptionOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->connection, $this->publications, $options));
    }
}
