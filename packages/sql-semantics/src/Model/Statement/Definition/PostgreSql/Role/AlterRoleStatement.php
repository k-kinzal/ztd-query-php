<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Role;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Role\ClearedPassword;
use SqlSemantics\Model\Definition\Role\ConnectionLimit;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleInvariant;
use SqlSemantics\Model\Definition\Role\RoleMembers;
use SqlSemantics\Model\Definition\Role\RolePassword;
use SqlSemantics\Model\Definition\Role\RoleValidity;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes the attributes of an existing role; ALTER USER binds to the same operation.
 *
 * A USER member list adds members, the same request as ALTER GROUP ADD USER.
 * @visibility public
 * @example Reading the changed attributes
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER USER CURRENT_USER WITH NOLOGIN USER alice');
 *     $statement->role // => \SqlSemantics\Model\Configuration\Role\SessionRole::CurrentUser
 *     $statement->options[0]->granted // => false
 *     $statement->options[1]->roles[0]->name // => 'alice'
 * @example Rejecting membership options outside a definition
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleStatement($origin, new \SqlSemantics\Model\Configuration\Role\NamedRole('app'), [new \SqlSemantics\Model\Definition\Role\RoleSystemId(1)]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterRoleStatement extends BoundStatement
{
    /**
     * @param list<RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers> $options Ordered attribute changes, at most one per property
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly NamedRole|SessionRole $role,
        public readonly array $options = [],
    ) {
        RoleInvariant::options($origin, $options, false);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the alteration while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->role, $this->options);
    }

    /**
     * Replaces the altered role.
     */
    public function withRole(NamedRole|SessionRole $role): self
    {
        return $this->changed(new self($this->origin, $role, $this->options));
    }

    /**
     * Replaces the complete ordered attribute request.
     * @param list<RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers> $options Replacement options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->role, $options));
    }
}
