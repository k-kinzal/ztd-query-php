<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Validation\Collections;

/**
 * WITH ROLE ALL EXCEPT r1: every granted role except the named ones.
 * @visibility public
 * @example Reading the excluded roles
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GRANT SELECT ON *.* TO u AS g WITH ROLE ALL EXCEPT r1');
 *     $statement->grantor->roles->excludedRoles[0]->username // => 'r1'
 */
final class RoleExclusion
{
    /**
     * @param non-empty-list<AccountName> $excludedRoles Ordered excluded role names
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly array $excludedRoles)
    {
        Collections::objects(Collections::nonEmpty($excludedRoles), AccountName::class);
    }
}
