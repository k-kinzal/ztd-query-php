<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Account;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * ALTER USER account n FACTOR INITIATE REGISTRATION: starts device registration for a numbered factor.
 * @visibility public
 * @example Reading the factor being registered
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER USER USER() 2 FACTOR INITIATE REGISTRATION');
 *     [$statement->account, $statement->factor->value] // => [\SqlSemantics\Model\Configuration\Account\ClientAccount::Connected, '2']
 */
final class InitiateRegistrationStatement extends BoundStatement
{
    /**
     * Requires the account and the factor; MySQL 8 only.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AccountName|CurrentAccount|ClientAccount $account, public readonly AuthenticationFactor $factor)
    {
        AccountForms::modern($origin, 'Factor registration');
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->account, $this->factor);
    }

    /**
     * Replaces the registering account.
     */
    public function withAccount(AccountName|CurrentAccount|ClientAccount $account): self
    {
        return $this->changed(new self($this->origin, $account, $this->factor));
    }

    /**
     * Replaces the factor being registered.
     */
    public function withFactor(AuthenticationFactor $factor): self
    {
        return $this->changed(new self($this->origin, $this->account, $factor));
    }
}
