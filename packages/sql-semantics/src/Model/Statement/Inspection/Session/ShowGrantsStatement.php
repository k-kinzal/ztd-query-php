<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Session;

use Override;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Session\GrantField;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Returns the GRANT statements that reproduce an account's privileges, optionally as if the listed roles were active.
 * @visibility public
 * @example Inspecting the described account and roles
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SHOW GRANTS FOR 'app'@'%' USING 'reader'");
 *     [$statement->account->username, $statement->roles[0]->username, $statement->resultColumns()[0]->name] // => ['app', 'reader', 'Grants for app@%']
 */
final class ShowGrantsStatement extends InspectionStatement
{
    /**
     * @param AccountName|CurrentAccount $account Described account; SHOW GRANTS without FOR describes the authenticated account
     * @param list<AccountName|CurrentAccount> $roles Roles whose privileges are shown as active, in request order; none shows the account's own grants
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AccountName|CurrentAccount $account = CurrentAccount::Authenticated, public readonly array $roles = [])
    {
        Collections::alternatives($roles, [AccountName::class, CurrentAccount::class]);
        if ($roles !== [] && in_array($origin->context?->schema()->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44'], true)) {
            throw new InvalidStructure('SHOW GRANTS ... USING requires MySQL 8.0 or later.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->account, $this->roles);
    }

    /**
     * Describes another account.
     */
    public function withAccount(AccountName|CurrentAccount $account): self
    {
        return $this->changed(new self($this->origin, $account, $this->roles));
    }

    /**
     * Replaces the roles shown as active; an empty list shows the account's own grants.
     * @param list<AccountName|CurrentAccount> $roles
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $this->account, $roles));
    }

    /**
     * @return list<OutputColumn> One grant field labelled with the account; an omitted host reads as the percent host
     */
    #[Override]
    public function resultColumns(): array
    {
        $account = $this->account instanceof CurrentAccount ? $this->account->value : $this->account->username . '@' . ($this->account->host ?? '%');
        return $this->columns(GrantField::cases(), [GrantField::Grants->label() => GrantField::Grants->label() . $account]);
    }
}
