<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Describes removal of MySQL accounts, keeping names and hosts separate.
 * @visibility public
 * @example Inspecting account identity
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("DROP USER 'reader'@'localhost'");
 *     $statement->accounts[0]->host // => 'localhost'
 * @example Rejecting a missing target list
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\MySql\DropUsersStatement($origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropUsersStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountName|CurrentAccount> $accounts Ordered removal targets
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $accounts, public readonly bool $ifExists = false)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('This account removal form requires MySQL.');
        }
        if ($ifExists && $origin->context?->schema()->grammarVersion === 'mysql-5.6.51') {
            throw new InvalidStructure('MySQL 5.6 account removal has no IF EXISTS policy.');
        }
        Collections::alternatives(Collections::nonEmpty($accounts), [AccountName::class, CurrentAccount::class]);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the complete removal request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->accounts, $this->ifExists);
    }

    /**
     * Replaces the targets without changing the original request.
     * @param non-empty-list<AccountName|CurrentAccount> $accounts Ordered replacement targets
     */
    public function withAccounts(array $accounts): self
    {
        return $this->changed(new self($this->origin, $accounts, $this->ifExists));
    }

    /**
     * Replaces the behavior when a target does not exist.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->accounts, $ifExists));
    }
}
