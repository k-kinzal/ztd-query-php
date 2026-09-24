<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicaChannel;
use SqlSemantics\Model\Configuration\Replication\ReplicaThread;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Stops replica threads (STOP REPLICA, or STOP SLAVE before MySQL 8.0.22).
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("STOP REPLICA IO_THREAD FOR CHANNEL 'east'");
 *     [$statement->threads[0]->value, $statement->channel] // => ['IO_THREAD', 'east']
 */
final class StopReplicaStatement extends BoundStatement
{
    /**
     * @param list<ReplicaThread> $threads Threads in request order; empty stops both
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $threads = [], public readonly ?string $channel = null)
    {
        ReplicationRelease::require($origin, 'STOP REPLICA');
        ReplicaChannel::check($origin, $channel);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Stop;
    }

    /**
     * Retains the threads and channel when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->threads, $this->channel);
    }

    /**
     * Replaces the stopped threads; an empty list stops both.
     * @param list<ReplicaThread> $threads
     */
    public function withThreads(array $threads): self
    {
        return $this->changed(new self($this->origin, $threads, $this->channel));
    }

    /**
     * Replaces the channel; null selects the default channel, or every channel when several exist.
     */
    public function withChannel(?string $channel): self
    {
        return $this->changed(new self($this->origin, $this->threads, $channel));
    }
}
