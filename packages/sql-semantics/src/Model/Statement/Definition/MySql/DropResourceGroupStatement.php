<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Describes removal of one named MySQL resource group without executing it.
 * @visibility public
 * @example Inspecting the deletion target
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('DROP RESOURCE GROUP target');
 *     $statement->name // => 'target'
 */
final class DropResourceGroupStatement extends BoundStatement
{
    /**
     * Keeps the identifier separate from the operation's removal policy.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly bool $force = false)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('This named removal form requires MySQL.');
        }
        if (in_array($origin->context?->schema()->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44'], true)) {
            throw new InvalidStructure('Resource groups require a MySQL release with resource-group support.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the target and policy while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->force);
    }

    /**
     * Replaces the target in a separately validated statement.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->force));
    }

    /**
     * Selects whether threads using the group must move to their default groups.
     */
    public function withForce(bool $force): self
    {
        return $this->changed(new self($this->origin, $this->name, $force));
    }
}
