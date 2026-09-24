<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Privilege;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Privilege\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\DatabaseScope;
use SqlSemantics\Model\Definition\Privilege\DynamicPrivilege;
use SqlSemantics\Model\Definition\Privilege\PrivilegeOperands;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Definition\Privilege\RoutineTarget;
use SqlSemantics\Model\Definition\Privilege\StaticPrivilege;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * REVOKE named privileges at one level from accounts.
 * @visibility public
 * @example Reading revoked privileges and policies
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('REVOKE IF EXISTS SELECT, DELETE ON app.* FROM u IGNORE UNKNOWN USER');
 *     [array_column($statement->privileges, 'value'), $statement->ifExists, $statement->ignoreUnknownUser] // => [['SELECT', 'DELETE'], true, true]
 * @example Rejecting an empty privilege list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('REVOKE SELECT ON *.* FROM u');
 *     $statement->withPrivileges([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RevokePrivilegesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<StaticPrivilege|ColumnPrivilege|DynamicPrivilege> $privileges Ordered privileges
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Ordered accounts losing the privileges
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $privileges,
        public readonly PrivilegeScope|DatabaseScope|TableReference|RoutineTarget $target,
        public readonly array $grantees,
        public readonly bool $ifExists = false,
        public readonly bool $ignoreUnknownUser = false,
    ) {
        PrivilegeOperands::privileges($origin, $privileges, $target);
        PrivilegeOperands::grantees($origin, $grantees, false);
        PrivilegeOperands::revocation($origin, $ifExists, $ignoreUnknownUser);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Revoke;
    }

    /**
     * Retains the complete revocation while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->privileges, $this->target, $this->grantees, $this->ifExists, $this->ignoreUnknownUser);
    }

    /**
     * Replaces the revoked privileges.
     * @param non-empty-list<StaticPrivilege|ColumnPrivilege|DynamicPrivilege> $privileges Replacement privileges
     */
    public function withPrivileges(array $privileges): self
    {
        return $this->changed(new self($this->origin, $privileges, $this->target, $this->grantees, $this->ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the privilege level.
     */
    public function withTarget(PrivilegeScope|DatabaseScope|TableReference|RoutineTarget $target): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $target, $this->grantees, $this->ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the accounts losing the privileges.
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Replacement accounts
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $grantees, $this->ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the behavior when a privilege was not granted.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $this->grantees, $ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the behavior when an account does not exist.
     */
    public function withIgnoreUnknownUser(bool $ignoreUnknownUser): self
    {
        return $this->changed(new self($this->origin, $this->privileges, $this->target, $this->grantees, $this->ifExists, $ignoreUnknownUser));
    }
}
