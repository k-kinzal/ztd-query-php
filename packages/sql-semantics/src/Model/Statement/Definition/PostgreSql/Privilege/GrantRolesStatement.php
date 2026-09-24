<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\PrivilegeInvariant;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantOption;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Grants membership in roles, with optional admin, inherit, and set options.
 * @visibility public
 * @example Reading a membership grant
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('GRANT staff, select TO alice WITH ADMIN OPTION, SET FALSE');
 *     $statement->roles[1]->name // => 'select'
 *     $statement->options[1]->granted // => false
 * @example Rejecting an empty role selection
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantRolesStatement($origin, [], [new \SqlSemantics\Model\Configuration\Role\NamedRole('alice')]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class GrantRolesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<NamedRole> $roles Roles whose membership is granted
     * @param non-empty-list<NamedRole|SessionRole> $grantees Roles becoming members
     * @param list<RoleGrantOption> $options Ordered membership options; a later value replaces an earlier one
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $roles,
        public readonly array $grantees,
        public readonly array $options = [],
        public readonly NamedRole|SessionRole|null $grantor = null,
    ) {
        PrivilegeInvariant::dialect($origin);
        Collections::objects(Collections::nonEmpty($roles), NamedRole::class);
        PrivilegeInvariant::grantees($grantees, false);
        Collections::objects($options, RoleGrantOption::class);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Grant;
    }

    /**
     * Retains the membership grant while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->roles, $this->grantees, $this->options, $this->grantor);
    }

    /**
     * Replaces the granted roles.
     * @param non-empty-list<NamedRole> $roles Replacement roles
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $roles, $this->grantees, $this->options, $this->grantor));
    }

    /**
     * Replaces the new members.
     * @param non-empty-list<NamedRole|SessionRole> $grantees Replacement members
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->roles, $grantees, $this->options, $this->grantor));
    }

    /**
     * Replaces the complete ordered option list.
     * @param list<RoleGrantOption> $options Replacement options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->roles, $this->grantees, $options, $this->grantor));
    }

    /**
     * Replaces or removes the role recorded as grantor.
     */
    public function withGrantor(NamedRole|SessionRole|null $grantor): self
    {
        return $this->changed(new self($this->origin, $this->roles, $this->grantees, $this->options, $grantor));
    }
}
