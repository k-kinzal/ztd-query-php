<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Administration;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Administration\ResetTarget;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Discards the listed binary log, replica and query cache state (MySQL RESET option, ...).
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("RESET REPLICA ALL FOR CHANNEL 'east'");
 *     $statement->targets[0]->channel // => 'east'
 */
final class ResetServerStatement extends BoundStatement
{
    /**
     * @var non-empty-list<ResetTarget>
     */
    public readonly array $targets;

    /**
     * @param list<ResetTarget> $targets Reset options in request order; at least one, each available in the release
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $targets)
    {
        ReplicationRelease::require($origin, 'RESET');
        Collections::objects($targets, ResetTarget::class);
        $release = ReplicationRelease::of($origin);
        foreach ($targets as $target) {
            if ($release !== null && !$target->availableIn($release)) {
                throw new InvalidStructure('The reset option is not available in this MySQL release.');
            }
        }
        $this->targets = Collections::nonEmpty($targets);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Reset;
    }

    /**
     * Retains the targets when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->targets);
    }

    /**
     * Replaces the reset options.
     * @param list<ResetTarget> $targets
     */
    public function withTargets(array $targets): self
    {
        return $this->changed(new self($this->origin, $targets));
    }
}
