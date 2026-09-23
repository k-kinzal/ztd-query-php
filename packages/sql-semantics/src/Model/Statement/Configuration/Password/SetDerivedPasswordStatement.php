<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Password;

use Override;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Account\PasswordDerivation;
use SqlSemantics\Model\Configuration\Account\PasswordOperands;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * Requests MySQL 5.6 password hashing using an explicit derivation policy.
 * @visibility public
 * @example Binding the credential operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = PASSWORD('new')");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\Password\SetDerivedPasswordStatement // => true
 */
final class SetDerivedPasswordStatement extends ConfigurationStatement
{
    /**
     * Validates the credential operand categories without changing account state.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AccountName|CurrentAccount $account, public readonly Literal $password, public readonly PasswordDerivation $derivation)
    {
        PasswordOperands::validate($origin, $password);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Set;
    }

    /**
     * Retains the account request when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->account, $this->password, $this->derivation);
    }

    /**
     * Replaces account in a new validated account request.
     */
    public function withAccount(AccountName|CurrentAccount $account): self
    {
        return $this->changed(new self($this->origin, $account, $this->password, $this->derivation));
    }

    /**
     * Replaces password in a new validated account request.
     */
    public function withPassword(Literal $password): self
    {
        return $this->changed(new self($this->origin, $this->account, $password, $this->derivation));
    }

    /**
     * Replaces derivation in a new validated account request.
     */
    public function withDerivation(PasswordDerivation $derivation): self
    {
        return $this->changed(new self($this->origin, $this->account, $this->password, $derivation));
    }
}
