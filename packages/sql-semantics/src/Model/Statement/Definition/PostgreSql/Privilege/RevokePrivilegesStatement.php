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
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\PrivilegeInvariant;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\LargeObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ParameterTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\TableTargets;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Revokes privileges, or only their grant option, from roles with a dependent-grant policy.
 * @visibility public
 * @example Reading a grant option revocation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('REVOKE GRANT OPTION FOR EXECUTE ON FUNCTION f(integer) FROM alice GRANTED BY bob CASCADE');
 *     $statement->grantOptionOnly // => true
 *     $statement->grantor->name // => 'bob'
 *     $statement->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Cascade
 */
final class RevokePrivilegesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<ObjectPrivilege|ColumnPrivilege> $privileges Ordered privilege requests
     * @param non-empty-list<NamedRole|SessionRole|PublicRole> $grantees Roles losing the privileges
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $privileges,
        public readonly TableTargets|SchemaObjectTargets|ServerObjectTargets|RoutineTargets|LargeObjectTargets|ParameterTargets|SchemaScopedTargets $target,
        public readonly array $grantees,
        public readonly bool $grantOptionOnly = false,
        public readonly NamedRole|SessionRole|null $grantor = null,
        public readonly DropBehavior $behavior = DropBehavior::Default,
    ) {
        PrivilegeInvariant::target($origin, $target, $privileges);
        PrivilegeInvariant::grantees($grantees, true);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Revoke;
    }

    /**
     * Retains the revocation while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->privileges, $this->target, $this->grantees, $this->grantOptionOnly, $this->grantor, $this->behavior);
    }

    /**
     * Replaces the complete privilege request.
     * @param non-empty-list<ObjectPrivilege|ColumnPrivilege> $privileges Replacement privileges
     */
    public function withPrivileges(array $privileges): self
    {
        return $this->changed(new self($this->origin, $privileges, $this->target, $this->grantees, $this->grantOptionOnly, $this->grantor, $this->behavior));
    }

    /**
     * Replaces the object selection.
     */
    public function withTarget(TableTargets|SchemaObjectTargets|ServerObjectTargets|RoutineTargets|LargeObjectTargets|ParameterTargets|SchemaScopedTargets $target): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $target, $this->grantees, $this->grantOptionOnly, $this->grantor, $this->behavior));
    }

    /**
     * Replaces the roles losing the privileges.
     * @param non-empty-list<NamedRole|SessionRole|PublicRole> $grantees Replacement roles
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $grantees, $this->grantOptionOnly, $this->grantor, $this->behavior));
    }

    /**
     * Replaces whether only the grant option is revoked.
     */
    public function withGrantOptionOnly(bool $grantOptionOnly): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $this->grantees, $grantOptionOnly, $this->grantor, $this->behavior));
    }

    /**
     * Replaces or removes the role whose grants are revoked.
     */
    public function withGrantor(NamedRole|SessionRole|null $grantor): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $this->grantees, $this->grantOptionOnly, $grantor, $this->behavior));
    }

    /**
     * Replaces the handling of dependent grants.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $this->grantees, $this->grantOptionOnly, $this->grantor, $behavior));
    }
}
