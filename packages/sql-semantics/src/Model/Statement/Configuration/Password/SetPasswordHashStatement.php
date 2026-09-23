<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Password;

use Override;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Account\PasswordOperands;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * Supplies an already encoded password hash in MySQL 5.6.
 * @visibility public
 * @example Binding the credential operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*encoded'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\Password\SetPasswordHashStatement // => true
 */
final class SetPasswordHashStatement extends ConfigurationStatement
{
    /**
     * Validates the credential operand categories without changing account state.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AccountName|CurrentAccount $account, public readonly Literal $hash)
    {
        PasswordOperands::validate($origin, $hash);
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
        return new self($origin, $this->account, $this->hash);
    }

    /**
     * Replaces account in a new validated account request.
     */
    public function withAccount(AccountName|CurrentAccount $account): self
    {
        return $this->changed(new self($this->origin, $account, $this->hash));
    }

    /**
     * Replaces hash in a new validated account request.
     */
    public function withHash(Literal $hash): self
    {
        return $this->changed(new self($this->origin, $this->account, $hash));
    }
}
