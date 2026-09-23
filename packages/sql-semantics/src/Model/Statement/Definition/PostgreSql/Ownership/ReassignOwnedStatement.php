<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Ownership\OwnershipInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Selects owned objects and a required new owner for ownership transfer.
 * @visibility public
 * @example Inspecting ownership selectors
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('REASSIGN OWNED BY alice TO SESSION_USER');
 *     $statement->owners[0]->name // => 'alice'
 * @example Rejecting an empty ownership selection
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership\ReassignOwnedStatement($origin, [], \SqlSemantics\Model\Configuration\Role\SessionRole::CurrentUser); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ReassignOwnedStatement extends BoundStatement
{
    /**
     * @param non-empty-list<NamedRole|SessionRole> $owners Roles whose objects are selected
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $owners,
        public readonly NamedRole|SessionRole $newOwner,
    ) {
        OwnershipInvariant::owners($origin, $owners);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Reassign;
    }

    /**
     * Retains the ownership request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->owners, $this->newOwner);
    }

    /**
     * Replaces the source roles in a separately validated ownership request.
     * @param non-empty-list<NamedRole|SessionRole> $owners Replacement selection
     */
    public function withOwners(array $owners): self
    {
        return $this->changed(new self($this->origin, $owners, $this->newOwner));
    }

    /**
     * Replaces the ownership destination without changing the source selection.
     */
    public function withNewOwner(NamedRole|SessionRole $newOwner): self
    {
        return $this->changed(new self($this->origin, $this->owners, $newOwner));
    }

}
