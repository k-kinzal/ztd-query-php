<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Privilege;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;

/**
 * GRANT roles TO accounts: MySQL 8 role membership, optionally with ADMIN OPTION.
 * @visibility public
 * @example Reading the granted roles and recipients
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("GRANT reader, 'writer'@'h' TO a, CURRENT_USER WITH ADMIN OPTION");
 *     [array_column($statement->roles, 'username'), $statement->withAdminOption] // => [['reader', 'writer'], true]
 * @example Rejecting an empty role list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GRANT reader TO a');
 *     $statement->withRoles([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class GrantRolesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountName> $roles Ordered roles
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Ordered recipients
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $roles, public readonly array $grantees, public readonly bool $withAdminOption = false)
    {
        AccountForms::modern($origin, 'Role membership');
        Collections::objects(Collections::nonEmpty($roles), AccountName::class);
        Collections::alternatives(Collections::nonEmpty($grantees), [AccountName::class, CurrentAccount::class]);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Grant;
    }

    /**
     * Retains the role grant while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->roles, $this->grantees, $this->withAdminOption);
    }

    /**
     * Replaces the granted roles.
     * @param non-empty-list<AccountName> $roles Replacement roles
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $roles, $this->grantees, $this->withAdminOption));
    }

    /**
     * Replaces the recipients.
     * @param non-empty-list<AccountName|CurrentAccount> $grantees Replacement recipients
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->roles, $grantees, $this->withAdminOption));
    }

    /**
     * Replaces whether recipients may grant the roles onward.
     */
    public function withWithAdminOption(bool $withAdminOption): self
    {
        return $this->changed(new self($this->origin, $this->roles, $this->grantees, $withAdminOption));
    }
}
