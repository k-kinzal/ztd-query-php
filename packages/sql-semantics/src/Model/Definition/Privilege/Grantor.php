<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Role\SessionRolePolicy;

/**
 * AS user [WITH ROLE ...]: the account whose privileges are checked instead of the caller's.
 * @visibility public
 * @example Reading a grantor with a role policy
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GRANT SELECT ON *.* TO u AS g WITH ROLE NONE');
 *     [$statement->grantor->account->username, $statement->grantor->roles] // => ['g', \SqlSemantics\Model\Configuration\Role\SessionRolePolicy::None]
 */
final class Grantor
{
    /**
     * An omitted role selection keeps the grantor's default roles.
     */
    public function __construct(
        public readonly AccountName|CurrentAccount $account,
        public readonly SessionRolePolicy|RoleSelection|RoleExclusion|null $roles = null,
    ) {
    }
}
