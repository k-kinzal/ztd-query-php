<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Privilege;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Definition\Privilege\PrivilegeOperands;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;

/**
 * REVOKE roles FROM accounts: MySQL 8 role membership removal.
 * @visibility public
 * @example Reading the revoked roles
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("REVOKE reader, 'writer'@'h' FROM a IGNORE UNKNOWN USER");
 *     [array_column($statement->roles, 'username'), $statement->ignoreUnknownUser] // => [['reader', 'writer'], true]
 * @example Rejecting an empty role list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('REVOKE reader FROM a');
 *     $statement->withRoles([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RevokeRolesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountName> $roles Ordered roles
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Ordered accounts losing the roles
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $roles, public readonly array $grantees, public readonly bool $ifExists = false, public readonly bool $ignoreUnknownUser = false)
    {
        AccountForms::modern($origin, 'Role membership');
        Collections::objects(Collections::nonEmpty($roles), AccountName::class);
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
     * Retains the revocation while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->roles, $this->grantees, $this->ifExists, $this->ignoreUnknownUser);
    }

    /**
     * Replaces the revoked roles.
     * @param non-empty-list<AccountName> $roles Replacement roles
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $roles, $this->grantees, $this->ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the accounts losing the roles.
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Replacement accounts
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->roles, $grantees, $this->ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the behavior when a role was not granted.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->roles, $this->grantees, $ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the behavior when an account does not exist.
     */
    public function withIgnoreUnknownUser(bool $ignoreUnknownUser): self
    {
        return $this->changed(new self($this->origin, $this->roles, $this->grantees, $this->ifExists, $ignoreUnknownUser));
    }
}
