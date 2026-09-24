<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\CredentialOption;
use SqlSemantics\Model\Configuration\Replication\ReplicaChannel;
use SqlSemantics\Model\Configuration\Replication\ReplicaThread;
use SqlSemantics\Model\Configuration\Replication\ReplicationCredential;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Configuration\Replication\UntilCondition;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Starts replica threads (START REPLICA, or START SLAVE before MySQL 8.0.22), optionally until a stop point and with connection credentials.
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("START REPLICA IO_THREAD USER = 'repl' FOR CHANNEL 'east'");
 *     [$statement->credentials[0]->option->value, $statement->channel] // => ['USER', 'east']
 */
final class StartReplicaStatement extends BoundStatement
{
    /**
     * @param list<ReplicaThread> $threads Threads in request order; empty starts both
     * @param list<ReplicationCredential> $credentials At most one of each option, in USER, PASSWORD, DEFAULT_AUTH, PLUGIN_DIR order
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $threads = [],
        public readonly ?UntilCondition $until = null,
        public readonly array $credentials = [],
        public readonly ?string $channel = null,
    ) {
        ReplicationRelease::require($origin, 'START REPLICA');
        ReplicaChannel::check($origin, $channel);
        Collections::objects($credentials, ReplicationCredential::class);
        $order = array_map(static fn (ReplicationCredential $credential): int => (int) array_search($credential->option, CredentialOption::cases(), true), $credentials);
        $sorted = array_values(array_unique($order));
        sort($sorted);
        if ($order !== $sorted) {
            throw new InvalidStructure('START REPLICA credentials appear at most once each, in USER, PASSWORD, DEFAULT_AUTH, PLUGIN_DIR order.');
        }
        if ($credentials !== [] && in_array(ReplicaThread::Applier, $threads, true) && !in_array(ReplicaThread::Receiver, $threads, true)) {
            throw new InvalidStructure('Connection credentials cannot accompany a request that starts only the SQL thread.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Start;
    }

    /**
     * Retains every operand when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->threads, $this->until, $this->credentials, $this->channel);
    }

    /**
     * Replaces the started threads; an empty list starts both.
     * @param list<ReplicaThread> $threads
     */
    public function withThreads(array $threads): self
    {
        return $this->changed(new self($this->origin, $threads, $this->until, $this->credentials, $this->channel));
    }

    /**
     * Replaces the stop point; null replicates without a stop point.
     */
    public function withUntil(?UntilCondition $until): self
    {
        return $this->changed(new self($this->origin, $this->threads, $until, $this->credentials, $this->channel));
    }

    /**
     * Replaces the connection credentials.
     * @param list<ReplicationCredential> $credentials
     */
    public function withCredentials(array $credentials): self
    {
        return $this->changed(new self($this->origin, $this->threads, $this->until, $credentials, $this->channel));
    }

    /**
     * Replaces the channel; null selects the default channel, or every channel when several exist.
     */
    public function withChannel(?string $channel): self
    {
        return $this->changed(new self($this->origin, $this->threads, $this->until, $this->credentials, $channel));
    }
}
