<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Account;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;

/**
 * ALTER USER account DEFAULT ROLE r1, r2: names the roles activated by default for one account.
 * @visibility public
 * @example Reading the default roles
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER USER 'a'@'h' DEFAULT ROLE reader, 'writer'@'h'");
 *     array_column($statement->roles, 'username') // => ['reader', 'writer']
 * @example Rejecting an empty role list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER USER a DEFAULT ROLE reader');
 *     $statement->withRoles([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterDefaultRolesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountName> $roles Ordered default roles
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AccountName|CurrentAccount $account, public readonly array $roles)
    {
        AccountForms::modern($origin, 'A default role list');
        Collections::objects(Collections::nonEmpty($roles), AccountName::class);
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
        return new self($origin, $this->account, $this->roles);
    }

    /**
     * Replaces the account whose defaults change.
     */
    public function withAccount(AccountName|CurrentAccount $account): self
    {
        return $this->changed(new self($this->origin, $account, $this->roles));
    }

    /**
     * Replaces the default roles.
     * @param non-empty-list<AccountName> $roles Replacement roles
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $this->account, $roles));
    }
}
