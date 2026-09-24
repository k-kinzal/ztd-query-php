<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Administration;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Administration\FlushTarget;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reloads or reopens the listed server caches and logs (FLUSH option, ...).
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('FLUSH LOCAL PRIVILEGES, BINARY LOGS');
 *     $statement->targets[1]->value // => 'BINARY LOGS'
 */
final class FlushServerStatement extends BoundStatement
{
    /**
     * @var non-empty-list<FlushTarget>
     */
    public readonly array $targets;

    /**
     * @param list<FlushTarget> $targets Flush options in request order; at least one, each available in the release
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $targets, public readonly BinlogPolicy $binlog = BinlogPolicy::Write)
    {
        ReplicationRelease::require($origin, 'FLUSH');
        Collections::objects($targets, FlushTarget::class);
        $release = ReplicationRelease::of($origin);
        foreach ($targets as $target) {
            if ($release !== null && !$target->availableIn($release)) {
                throw new InvalidStructure('The flush option is not available in this MySQL release.');
            }
        }
        $this->targets = Collections::nonEmpty($targets);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Flush;
    }

    /**
     * Retains the targets and binary log policy when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->targets, $this->binlog);
    }

    /**
     * Replaces the flush options.
     * @param list<FlushTarget> $targets
     */
    public function withTargets(array $targets): self
    {
        return $this->changed(new self($this->origin, $targets, $this->binlog));
    }

    /**
     * Chooses whether the request is written to the binary log.
     */
    public function withBinlog(BinlogPolicy $binlog): self
    {
        return $this->changed(new self($this->origin, $this->targets, $binlog));
    }
}
