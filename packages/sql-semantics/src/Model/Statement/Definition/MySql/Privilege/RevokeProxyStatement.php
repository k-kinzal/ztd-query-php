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
 * REVOKE PROXY ON account FROM accounts: the recipients may no longer act as the proxied account.
 * @visibility public
 * @example Reading the proxied account
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("REVOKE IF EXISTS PROXY ON 'p'@'h' FROM a");
 *     [$statement->proxied->username, $statement->ifExists] // => ['p', true]
 */
final class RevokeProxyStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Ordered accounts losing the proxy privilege
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AccountName|CurrentAccount $proxied, public readonly array $grantees, public readonly bool $ifExists = false, public readonly bool $ignoreUnknownUser = false)
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
        return new self($origin, $this->proxied, $this->grantees, $this->ifExists, $this->ignoreUnknownUser);
    }

    /**
     * Replaces the proxied account.
     */
    public function withProxied(AccountName|CurrentAccount $proxied): self
    {
        return $this->changed(new self($this->origin, $proxied, $this->grantees, $this->ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the accounts losing the proxy privilege.
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Replacement accounts
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->proxied, $grantees, $this->ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the behavior when the proxy privilege was not granted.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->proxied, $this->grantees, $ifExists, $this->ignoreUnknownUser));
    }

    /**
     * Replaces the behavior when an account does not exist.
     */
    public function withIgnoreUnknownUser(bool $ignoreUnknownUser): self
    {
        return $this->changed(new self($this->origin, $this->proxied, $this->grantees, $this->ifExists, $ignoreUnknownUser));
    }
}
