<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Replication\Subscription\PublicationListChange;
use SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionInvariant;
use SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionOptions;
use SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionParameter;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Replaces, adds to, or removes from the publications of a subscription, optionally refreshing its tables.
 * @visibility public
 * @example Reading a publication change of a subscription
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION sub SET PUBLICATION pub WITH (refresh = false)');
 *     $statement->publications // => ['pub']
 *     $statement->options->refresh // => false
 */
final class AlterSubscriptionPublicationsStatement extends BoundStatement
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
        public readonly PublicationListChange $change,
        array $publications,
        public readonly SubscriptionOptions $options = new SubscriptionOptions(),
    ) {
        SubscriptionInvariant::identity($origin, $name);
        $this->publications = SubscriptionInvariant::publications($publications);
        SubscriptionInvariant::options($options, [SubscriptionParameter::Refresh, SubscriptionParameter::CopyData]);
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
        return new self($origin, $this->name, $this->change, $this->publications, $this->options);
    }

    /**
     * Replaces the subscription name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->change, $this->publications, $this->options));
    }

    /**
     * Replaces how the publications change the subscription.
     */
    public function withChange(PublicationListChange $change): self
    {
        return $this->changed(new self($this->origin, $this->name, $change, $this->publications, $this->options));
    }

    /**
     * Replaces the publication names.
     * @param list<string> $publications
     */
    public function withPublications(array $publications): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->change, $publications, $this->options));
    }

    /**
     * Replaces the WITH options, which must suit this command.
     */
    public function withOptions(SubscriptionOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->change, $this->publications, $options));
    }
}
