<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Role;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Role\ClearedPassword;
use SqlSemantics\Model\Definition\Role\ConnectionLimit;
use SqlSemantics\Model\Definition\Role\RoleAdmins;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleInvariant;
use SqlSemantics\Model\Definition\Role\RoleKeyword;
use SqlSemantics\Model\Definition\Role\RoleMembers;
use SqlSemantics\Model\Definition\Role\RoleMemberships;
use SqlSemantics\Model\Definition\Role\RolePassword;
use SqlSemantics\Model\Definition\Role\RoleSystemId;
use SqlSemantics\Model\Definition\Role\RoleValidity;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Defines a new role, user, or group with typed attributes and initial memberships.
 * @visibility public
 * @example Reading the definition
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE USER app WITH LOGIN CONNECTION LIMIT 10');
 *     $statement->name->name // => 'app'
 *     $statement->keyword // => \SqlSemantics\Model\Definition\Role\RoleKeyword::User
 *     $statement->options[1]->limit // => 10
 * @example Rejecting contradictory attributes
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
 *     $login = new \SqlSemantics\Model\Definition\Role\RoleAttribute(\SqlSemantics\Model\Definition\Role\RoleCapability::Login, true);
 *     $noLogin = new \SqlSemantics\Model\Definition\Role\RoleAttribute(\SqlSemantics\Model\Definition\Role\RoleCapability::Login, false);
 *     new \SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement($origin, new \SqlSemantics\Model\Configuration\Role\NamedRole('app'), [$login, $noLogin]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateRoleStatement extends BoundStatement
{
    /**
     * @param list<RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers|RoleMemberships|RoleAdmins|RoleSystemId> $options Ordered role options, at most one per property
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly NamedRole $name,
        public readonly array $options = [],
        public readonly RoleKeyword $keyword = RoleKeyword::Role,
    ) {
        RoleInvariant::options($origin, $options, true);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->options, $this->keyword);
    }

    /**
     * Replaces the defined role name.
     */
    public function withName(NamedRole $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->options, $this->keyword));
    }

    /**
     * Replaces the complete ordered option request.
     * @param list<RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers|RoleMemberships|RoleAdmins|RoleSystemId> $options Replacement options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options, $this->keyword));
    }

    /**
     * Replaces the definition keyword, which changes the default LOGIN attribute.
     */
    public function withKeyword(RoleKeyword $keyword): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->options, $keyword));
    }
}
