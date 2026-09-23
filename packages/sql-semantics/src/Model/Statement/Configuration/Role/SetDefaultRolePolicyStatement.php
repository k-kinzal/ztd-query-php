<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Role;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Role\DefaultRolePolicy;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes default role selection for a required list of accounts.
 * @visibility public
 * @example Binding the role operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET DEFAULT ROLE ALL TO 'user'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\Role\SetDefaultRolePolicyStatement // => true
 * @example Rejecting empty accounts
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET DEFAULT ROLE NONE TO 'u'");
 *     new \SqlSemantics\Model\Statement\Configuration\Role\SetDefaultRolePolicyStatement($statement->origin, $statement->policy, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetDefaultRolePolicyStatement extends ConfigurationStatement
{
    /**
     * @param non-empty-list<AccountName> $accounts
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly DefaultRolePolicy $policy, public readonly array $accounts)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('MySQL role selection requires MySQL.');
        }
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
        return new self($origin, $this->policy, $this->accounts);
    }

    /**
     * Replaces policy in a new validated operation.
     */
    public function withPolicy(DefaultRolePolicy $policy): self
    {
        return $this->changed(new self($this->origin, $policy, $this->accounts));
    }

    /**
     * Replaces accounts in a new validated operation.
     * @param non-empty-list<AccountName> $accounts
     */
    public function withAccounts(array $accounts): self
    {
        return $this->changed(new self($this->origin, $this->policy, $accounts));
    }
}
