<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Account;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Role\DefaultRolePolicy;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * ALTER USER account DEFAULT ROLE ALL or NONE: selects all granted roles or none as the account's defaults.
 * @visibility public
 * @example Reading the default role policy
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER USER CURRENT_USER() DEFAULT ROLE ALL');
 *     [$statement->account, $statement->policy] // => [\SqlSemantics\Model\Configuration\Account\CurrentAccount::Authenticated, \SqlSemantics\Model\Configuration\Role\DefaultRolePolicy::All]
 */
final class AlterDefaultRolePolicyStatement extends BoundStatement
{
    /**
     * Requires the account and the policy; MySQL 8 only.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AccountName|CurrentAccount $account, public readonly DefaultRolePolicy $policy)
    {
        AccountForms::modern($origin, 'A default role policy');
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
        return new self($origin, $this->account, $this->policy);
    }

    /**
     * Replaces the account whose defaults change.
     */
    public function withAccount(AccountName|CurrentAccount $account): self
    {
        return $this->changed(new self($this->origin, $account, $this->policy));
    }

    /**
     * Replaces the default role policy.
     */
    public function withPolicy(DefaultRolePolicy $policy): self
    {
        return $this->changed(new self($this->origin, $this->account, $policy));
    }
}
