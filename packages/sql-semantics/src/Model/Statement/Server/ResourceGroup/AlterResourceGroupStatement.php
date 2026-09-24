<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\ResourceGroup;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\ResourceGroup\CpuRange;
use SqlSemantics\Model\Configuration\ResourceGroup\ResourceGroupOptions;
use SqlSemantics\Model\Configuration\ResourceGroup\ResourceGroupState;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes the CPUs, thread priority or state of a resource group; FORCE moves threads out of a group being disabled.
 * @visibility public
 * @example Reading the requested changes
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER RESOURCE GROUP batch THREAD_PRIORITY = 5 DISABLE FORCE');
 *     [$statement->cpus, $statement->priority, $statement->state?->value, $statement->force] // => [[], 5, 'DISABLE', true]
 */
final class AlterResourceGroupStatement extends BoundStatement
{
    /**
     * @param list<CpuRange> $cpus Replacement CPU ranges; empty keeps the current CPUs
     * @param int|null $priority Replacement thread priority; null keeps the current priority
     * @param ResourceGroupState|null $state Replacement state; null keeps the current state
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly array $cpus = [], public readonly ?int $priority = null, public readonly ?ResourceGroupState $state = null, public readonly bool $force = false)
    {
        ResourceGroupOptions::validate($origin, $name, $cpus, $priority);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the requested changes while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->cpus, $this->priority, $this->state, $this->force);
    }

    /**
     * Changes another group.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->cpus, $this->priority, $this->state, $this->force));
    }

    /**
     * Replaces the CPU ranges; an empty list keeps the current CPUs.
     * @param list<CpuRange> $cpus
     */
    public function withCpus(array $cpus): self
    {
        return $this->changed(new self($this->origin, $this->name, $cpus, $this->priority, $this->state, $this->force));
    }

    /**
     * Replaces or omits the thread priority change.
     */
    public function withPriority(?int $priority): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->cpus, $priority, $this->state, $this->force));
    }

    /**
     * Replaces or omits the state change.
     */
    public function withState(?ResourceGroupState $state): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->cpus, $this->priority, $state, $this->force));
    }

    /**
     * Requests or omits FORCE.
     */
    public function withForce(bool $force): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->cpus, $this->priority, $this->state, $force));
    }
}
