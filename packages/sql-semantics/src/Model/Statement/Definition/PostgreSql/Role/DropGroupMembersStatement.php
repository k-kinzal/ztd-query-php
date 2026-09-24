<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Role;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Role\RoleInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The legacy group form that removes members of a role.
 * @visibility public
 * @example Reading the membership change
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER GROUP staff DROP USER alice, CURRENT_USER');
 *     $statement->group->name // => 'staff'
 *     $statement->members[1] // => \SqlSemantics\Model\Configuration\Role\SessionRole::CurrentUser
 * @example Rejecting an empty member list
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\PostgreSql\Role\DropGroupMembersStatement($origin, new \SqlSemantics\Model\Configuration\Role\NamedRole('staff'), []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropGroupMembersStatement extends BoundStatement
{
    /**
     * @param non-empty-list<NamedRole|SessionRole> $members Roles whose membership changes
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly NamedRole|SessionRole $group,
        public readonly array $members,
    ) {
        RoleInvariant::dialect($origin);
        RoleInvariant::roles($members);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the membership change while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->group, $this->members);
    }

    /**
     * Replaces the group whose membership changes.
     */
    public function withGroup(NamedRole|SessionRole $group): self
    {
        return $this->changed(new self($this->origin, $group, $this->members));
    }

    /**
     * Replaces the complete member selection.
     * @param non-empty-list<NamedRole|SessionRole> $members Replacement members
     */
    public function withMembers(array $members): self
    {
        return $this->changed(new self($this->origin, $this->group, $members));
    }
}
