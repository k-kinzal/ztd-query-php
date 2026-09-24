<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\ResourceGroup;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\ResourceGroup\CpuRange;
use SqlSemantics\Model\Configuration\ResourceGroup\ResourceGroupOptions;
use SqlSemantics\Model\Configuration\ResourceGroup\ResourceGroupState;
use SqlSemantics\Model\Configuration\ResourceGroup\ThreadCategory;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a resource group of a type, with the CPUs its threads may use, their priority and whether it is enabled.
 * @visibility public
 * @example Reading the group definition
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE RESOURCE GROUP batch TYPE = USER VCPU = 2-3 THREAD_PRIORITY = 10 DISABLE');
 *     [$statement->name, $statement->type->value, $statement->priority, $statement->state->value] // => ['batch', 'USER', 10, 'DISABLE']
 */
final class CreateResourceGroupStatement extends BoundStatement
{
    /**
     * @param list<CpuRange> $cpus CPU ranges in request order; empty allows every CPU
     * @param int|null $priority Thread priority within the type's range; null uses the default priority 0
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly ThreadCategory $type, public readonly array $cpus = [], public readonly ?int $priority = null, public readonly ResourceGroupState $state = ResourceGroupState::Enabled)
    {
        ResourceGroupOptions::validate($origin, $name, $cpus, $priority, $type);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the group definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->type, $this->cpus, $this->priority, $this->state);
    }

    /**
     * Creates the group under another name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->type, $this->cpus, $this->priority, $this->state));
    }

    /**
     * Creates a group of another type; the priority must suit it.
     */
    public function withType(ThreadCategory $type): self
    {
        return $this->changed(new self($this->origin, $this->name, $type, $this->cpus, $this->priority, $this->state));
    }

    /**
     * Replaces the CPU ranges; an empty list allows every CPU.
     * @param list<CpuRange> $cpus
     */
    public function withCpus(array $cpus): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $cpus, $this->priority, $this->state));
    }

    /**
     * Replaces or removes the thread priority.
     */
    public function withPriority(?int $priority): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $this->cpus, $priority, $this->state));
    }

    /**
     * Creates the group enabled or disabled.
     */
    public function withState(ResourceGroupState $state): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->type, $this->cpus, $this->priority, $state));
    }
}
