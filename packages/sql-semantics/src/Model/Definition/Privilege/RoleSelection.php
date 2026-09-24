<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Validation\Collections;

/**
 * WITH ROLE r1, r2: the explicit roles active for the grantor context.
 * @visibility public
 * @example Reading the grantor roles
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GRANT SELECT ON *.* TO u AS g WITH ROLE r1, r2');
 *     array_column($statement->grantor->roles->roles, 'username') // => ['r1', 'r2']
 */
final class RoleSelection
{
    /**
     * @param non-empty-list<AccountName> $roles Ordered role names
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly array $roles)
    {
        Collections::objects(Collections::nonEmpty($roles), AccountName::class);
    }
}
