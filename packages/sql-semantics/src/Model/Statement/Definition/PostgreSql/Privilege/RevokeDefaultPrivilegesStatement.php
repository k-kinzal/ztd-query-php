<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\DefaultPrivilegeTarget;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\PrivilegeInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes default privileges, or only their grant option, for objects created later.
 * @visibility public
 * @example Reading the default revocation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR ALL ON ROUTINES FROM alice CASCADE');
 *     $statement->grantOptionOnly // => true
 *     $statement->target // => \SqlSemantics\Model\Definition\Privilege\PostgreSql\DefaultPrivilegeTarget::Functions
 *     $statement->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Cascade
 */
final class RevokeDefaultPrivilegesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<ObjectPrivilege|ColumnPrivilege> $privileges Ordered privilege requests without column lists
     * @param non-empty-list<NamedRole|SessionRole|PublicRole> $grantees Roles losing the defaults
     * @param list<NamedRole|SessionRole> $roles Roles whose future objects are affected; empty means the current role
     * @param list<string> $schemas Schemas whose future objects are affected; empty means every schema
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $privileges,
        public readonly DefaultPrivilegeTarget $target,
        public readonly array $grantees,
        public readonly bool $grantOptionOnly = false,
        public readonly DropBehavior $behavior = DropBehavior::Default,
        public readonly array $roles = [],
        public readonly array $schemas = [],
    ) {
        PrivilegeInvariant::defaults($origin, $target, $privileges, $roles, $schemas);
        PrivilegeInvariant::grantees($grantees, true);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the default revocation while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->privileges, $this->target, $this->grantees, $this->grantOptionOnly, $this->behavior, $this->roles, $this->schemas);
    }

    /**
     * Replaces the complete privilege request.
     * @param non-empty-list<ObjectPrivilege|ColumnPrivilege> $privileges Replacement privileges
     */
    public function withPrivileges(array $privileges): self
    {
        return $this->changed(new self($this->origin, $privileges, $this->target, $this->grantees, $this->grantOptionOnly, $this->behavior, $this->roles, $this->schemas));
    }

    /**
     * Replaces the affected object class.
     */
    public function withTarget(DefaultPrivilegeTarget $target): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $target, $this->grantees, $this->grantOptionOnly, $this->behavior, $this->roles, $this->schemas));
    }

    /**
     * Replaces the roles losing the defaults.
     * @param non-empty-list<NamedRole|SessionRole|PublicRole> $grantees Replacement roles
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $grantees, $this->grantOptionOnly, $this->behavior, $this->roles, $this->schemas));
    }

    /**
     * Replaces whether only the grant option is revoked.
     */
    public function withGrantOptionOnly(bool $grantOptionOnly): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $this->grantees, $grantOptionOnly, $this->behavior, $this->roles, $this->schemas));
    }

    /**
     * Replaces the handling of dependent grants.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $this->grantees, $this->grantOptionOnly, $behavior, $this->roles, $this->schemas));
    }

    /**
     * Replaces the roles whose future objects are affected.
     * @param list<NamedRole|SessionRole> $roles Replacement roles
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $this->grantees, $this->grantOptionOnly, $this->behavior, $roles, $this->schemas));
    }

    /**
     * Replaces the schema selection.
     * @param list<string> $schemas Replacement schemas
     */
    public function withSchemas(array $schemas): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $this->grantees, $this->grantOptionOnly, $this->behavior, $this->roles, $schemas));
    }
}
