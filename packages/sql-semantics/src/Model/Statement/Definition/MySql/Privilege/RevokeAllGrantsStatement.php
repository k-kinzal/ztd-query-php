<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Privilege;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Privilege\PrivilegeOperands;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * REVOKE ALL [PRIVILEGES], GRANT OPTION FROM accounts: removes every privilege at every level, without naming one.
 * @visibility public
 * @example Reading the accounts losing all grants
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'u'@'h', CURRENT_USER()");
 *     [$statement->grantees[0]->host, $statement->grantees[1]] // => ['h', \SqlSemantics\Model\Configuration\Account\CurrentAccount::Authenticated]
 * @example Rejecting an empty account list
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\MySql\Privilege\RevokeAllGrantsStatement($origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RevokeAllGrantsStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Ordered accounts losing every grant
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $grantees, public readonly bool $ifExists = false, public readonly bool $ignoreUnknownUser = false)
    {
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
        return new self($origin, $this->grantees, $this->ifExists, $this->ignoreUnknownUser);
    }

    /**
     * Replaces the accounts losing every grant.
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Replacement accounts
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $grantees, $this->ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the behavior when nothing was granted.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->grantees, $ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the behavior when an account does not exist.
     */
    public function withIgnoreUnknownUser(bool $ignoreUnknownUser): self
    {
        return $this->changed(new self($this->origin, $this->grantees, $this->ifExists, $ignoreUnknownUser));
    }
}
