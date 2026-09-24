<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Role;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Role\RoleInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes named roles; DROP USER and DROP GROUP bind to the same operation.
 * @visibility public
 * @example Reading the removal
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP USER IF EXISTS alice, bob');
 *     $statement->ifExists // => true
 *     $statement->roles[1]->name // => 'bob'
 * @example Rejecting an empty selection
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropRolesStatement($origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropRolesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<NamedRole> $roles Ordered concrete role names
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $roles,
        public readonly bool $ifExists = false,
    ) {
        RoleInvariant::dialect($origin);
        Collections::objects(Collections::nonEmpty($roles), NamedRole::class);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the removal while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->roles, $this->ifExists);
    }

    /**
     * Replaces the complete role selection.
     * @param non-empty-list<NamedRole> $roles Replacement selection
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $roles, $this->ifExists));
    }

    /**
     * Replaces the behavior when a role does not exist.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->roles, $ifExists));
    }
}
