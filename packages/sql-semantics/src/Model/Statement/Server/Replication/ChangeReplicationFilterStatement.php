<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\Filter\ReplicationFilter;
use SqlSemantics\Model\Configuration\Replication\ReplicaChannel;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Replaces replication filter rules (CHANGE REPLICATION FILTER, MySQL 5.7 and later; FOR CHANNEL from MySQL 8.0).
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_DO_DB = (sales), REPLICATE_IGNORE_TABLE = (sales.audit) FOR CHANNEL 'east'");
 *     [$statement->filters[1]->rule()->value, $statement->channel] // => ['REPLICATE_IGNORE_TABLE', 'east']
 */
final class ChangeReplicationFilterStatement extends BoundStatement
{
    /**
     * @var non-empty-list<ReplicationFilter>
     */
    public readonly array $filters;

    /**
     * @param list<ReplicationFilter> $filters Filter rules, each rule at most once; at least one
     * @param ?string $channel The FOR CHANNEL name; null applies the rules to every channel
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $filters, public readonly ?string $channel = null)
    {
        ReplicationRelease::require($origin, 'CHANGE REPLICATION FILTER', 50700);
        if ($channel !== null) {
            ReplicationRelease::require($origin, 'CHANGE REPLICATION FILTER ... FOR CHANNEL', 80000);
        }
        ReplicaChannel::check($origin, $channel);
        Collections::objects($filters, ReplicationFilter::class);
        $rules = array_map(static fn (ReplicationFilter $filter): string => $filter->rule()->value, $filters);
        if (count($rules) !== count(array_unique($rules))) {
            throw new InvalidStructure('CHANGE REPLICATION FILTER sets each rule once.');
        }
        $this->filters = Collections::nonEmpty($filters);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Change;
    }

    /**
     * Retains the filters and channel when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->filters, $this->channel);
    }

    /**
     * Replaces the filter rules.
     * @param non-empty-list<ReplicationFilter> $filters
     */
    public function withFilters(array $filters): self
    {
        return $this->changed(new self($this->origin, $filters, $this->channel));
    }

    /**
     * Replaces the channel; null applies the rules to every channel.
     */
    public function withChannel(?string $channel): self
    {
        return $this->changed(new self($this->origin, $this->filters, $channel));
    }
}
