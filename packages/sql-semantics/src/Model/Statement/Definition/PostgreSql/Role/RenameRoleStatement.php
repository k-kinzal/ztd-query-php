<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Role;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Role\RoleInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Renames a role; ALTER USER and ALTER GROUP RENAME bind to the same operation.
 * @visibility public
 * @example Reading both names
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff RENAME TO crew');
 *     $statement->role->name // => 'staff'
 *     $statement->newName->name // => 'crew'
 */
final class RenameRoleStatement extends BoundStatement
{
    /**
     * Both names must be concrete; session role references cannot be renamed.
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly NamedRole $role,
        public readonly NamedRole $newName,
    ) {
        RoleInvariant::dialect($origin);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the rename while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->role, $this->newName);
    }

    /**
     * Replaces the renamed role.
     */
    public function withRole(NamedRole $role): self
    {
        return $this->changed(new self($this->origin, $role, $this->newName));
    }

    /**
     * Replaces the destination name.
     */
    public function withNewName(NamedRole $newName): self
    {
        return $this->changed(new self($this->origin, $this->role, $newName));
    }
}
