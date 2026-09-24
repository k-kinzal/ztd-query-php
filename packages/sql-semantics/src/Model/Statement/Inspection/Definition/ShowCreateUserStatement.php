<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Definition;

use Override;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateUserField;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Returns the CREATE USER statement that would recreate an account; the result label carries the account as written.
 * @visibility public
 * @example Inspecting the described account
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SHOW CREATE USER 'app'@'localhost'");
 *     [$statement->account->username, $statement->resultColumns()[0]->name] // => ['app', 'CREATE USER for app@localhost']
 */
final class ShowCreateUserStatement extends InspectionStatement
{
    /**
     * @param AccountName|CurrentAccount $account Described account, or the authenticated account
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AccountName|CurrentAccount $account)
    {
        if ($origin->context?->schema()->grammarVersion === 'mysql-5.6.51') {
            throw new InvalidStructure('SHOW CREATE USER requires MySQL 5.7 or later.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->account);
    }

    /**
     * Describes another account.
     */
    public function withAccount(AccountName|CurrentAccount $account): self
    {
        return $this->changed(new self($this->origin, $account));
    }

    /**
     * @return list<OutputColumn> One definition field labelled with the account; an omitted host reads as the percent host
     */
    #[Override]
    public function resultColumns(): array
    {
        $account = $this->account instanceof CurrentAccount ? $this->account->value : $this->account->username . '@' . ($this->account->host ?? '%');
        return $this->columns(CreateUserField::cases(), [CreateUserField::Definition->label() => CreateUserField::Definition->label() . $account]);
    }
}
