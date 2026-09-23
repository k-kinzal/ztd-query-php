<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Role;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Assigns a required list of default roles to required named accounts.
 * @visibility public
 * @example Binding the role operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET DEFAULT ROLE 'reader' TO 'user'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\Role\SetDefaultRolesStatement // => true
 * @example Rejecting empty roles
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET DEFAULT ROLE 'r' TO 'u'");
 *     new \SqlSemantics\Model\Statement\Configuration\Role\SetDefaultRolesStatement($statement->origin, [], $statement->accounts); // throws \SqlSemantics\Model\Validation\InvalidStructure
 * @example Rejecting empty accounts
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET DEFAULT ROLE 'r' TO 'u'");
 *     new \SqlSemantics\Model\Statement\Configuration\Role\SetDefaultRolesStatement($statement->origin, $statement->roles, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetDefaultRolesStatement extends ConfigurationStatement
{
    /**
     * @param non-empty-list<AccountName> $roles
     * @param non-empty-list<AccountName> $accounts
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $roles, public readonly array $accounts)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('MySQL role selection requires MySQL.');
        }
        Collections::objects($roles, AccountName::class);
        Collections::nonEmpty($roles);
        Collections::objects($accounts, AccountName::class);
        Collections::nonEmpty($accounts);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Set;
    }

    /**
     * Retains the role request while changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->roles, $this->accounts);
    }

    /**
     * Replaces roles in a new validated operation.
     * @param non-empty-list<AccountName> $roles
     */
    public function withRoles(array $roles): self
    {
        return $this->changed(new self($this->origin, $roles, $this->accounts));
    }

    /**
     * Replaces accounts in a new validated operation.
     * @param non-empty-list<AccountName> $accounts
     */
    public function withAccounts(array $accounts): self
    {
        return $this->changed(new self($this->origin, $this->roles, $accounts));
    }
}
