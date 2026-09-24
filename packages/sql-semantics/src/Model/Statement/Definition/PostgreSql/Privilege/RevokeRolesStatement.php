<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\PrivilegeInvariant;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantAttribute;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Revokes membership in roles, or only one membership option, with a dependent-grant policy.
 * @visibility public
 * @example Reading an option revocation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('REVOKE ADMIN OPTION FOR staff FROM alice RESTRICT');
 *     $statement->option // => \SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantAttribute::Admin
 *     $statement->behavior // => \SqlSemantics\Model\Definition\DropBehavior::Restrict
 */
final class RevokeRolesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<NamedRole> $roles Roles whose membership is revoked
     * @param non-empty-list<NamedRole|SessionRole> $grantees Roles losing membership
     * @param ?RoleGrantAttribute $option The single option revoked instead of the membership itself
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $roles,
        public readonly array $grantees,
        public readonly ?RoleGrantAttribute $option = null,
        public readonly NamedRole|SessionRole|null $grantor = null,
        public readonly DropBehavior $behavior = DropBehavior::Default,
    ) {
        PrivilegeInvariant::dialect($origin);
        Collections::objects(Collections::nonEmpty($roles), NamedRole::class);
        PrivilegeInvariant::grantees($grantees, false);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Revoke;
    }

    /**
     * Retains the membership revocation while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->roles, $this->grantees, $this->option, $this->grantor, $this->behavior);
    }

    /**
     * Replaces the revoked roles.
     * @param non-empty-list<NamedRole> $roles Replacement roles
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $roles, $this->grantees, $this->option, $this->grantor, $this->behavior));
    }

    /**
     * Replaces the roles losing membership.
     * @param non-empty-list<NamedRole|SessionRole> $grantees Replacement roles
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->roles, $grantees, $this->option, $this->grantor, $this->behavior));
    }

    /**
     * Replaces or removes the option-only restriction.
     */
    public function withOption(?RoleGrantAttribute $option): self
    {
        return $this->changed(new self($this->origin, $this->roles, $this->grantees, $option, $this->grantor, $this->behavior));
    }

    /**
     * Replaces or removes the role whose grants are revoked.
     */
    public function withGrantor(NamedRole|SessionRole|null $grantor): self
    {
        return $this->changed(new self($this->origin, $this->roles, $this->grantees, $this->option, $grantor, $this->behavior));
    }

    /**
     * Replaces the handling of dependent grants.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->roles, $this->grantees, $this->option, $this->grantor, $behavior));
    }
}
