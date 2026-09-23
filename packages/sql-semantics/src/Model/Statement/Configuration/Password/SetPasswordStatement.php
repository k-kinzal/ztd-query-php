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
 * Supplies a cleartext credential to the account authentication plugin.
 * @visibility public
 * @example Binding the credential operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET PASSWORD = 'new'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\Password\SetPasswordStatement // => true
 */
final class SetPasswordStatement extends ConfigurationStatement
{
    /**
     * Validates the credential operand categories without changing account state.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AccountName|CurrentAccount $account, public readonly Literal $password, public readonly ?Literal $currentPassword = null, public readonly bool $retainCurrentPassword = false)
    {
        PasswordOperands::validate($origin, $password, ...($currentPassword === null ? [] : [$currentPassword]));
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
        return new self($origin, $this->account, $this->password, $this->currentPassword, $this->retainCurrentPassword);
    }

    /**
     * Replaces account in a new validated account request.
     */
    public function withAccount(AccountName|CurrentAccount $account): self
    {
        return $this->changed(new self($this->origin, $account, $this->password, $this->currentPassword, $this->retainCurrentPassword));
    }

    /**
     * Replaces password in a new validated account request.
     */
    public function withPassword(Literal $password): self
    {
        return $this->changed(new self($this->origin, $this->account, $password, $this->currentPassword, $this->retainCurrentPassword));
    }

    /**
     * Replaces currentPassword in a new validated account request.
     */
    public function withCurrentPassword(?Literal $currentPassword): self
    {
        return $this->changed(new self($this->origin, $this->account, $this->password, $currentPassword, $this->retainCurrentPassword));
    }

    /**
     * Replaces retainCurrentPassword in a new validated account request.
     */
    public function withRetainCurrentPassword(bool $retainCurrentPassword): self
    {
        return $this->changed(new self($this->origin, $this->account, $this->password, $this->currentPassword, $retainCurrentPassword));
    }
}
