<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;

/**
 * One RENAME USER pair; both sides may be the authenticated account symbol.
 * @visibility public
 * @example Reading a rename pair
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("RENAME USER 'a'@'h' TO CURRENT_USER()");
 *     [$statement->renames[0]->from->username, $statement->renames[0]->to] // => ['a', \SqlSemantics\Model\Configuration\Account\CurrentAccount::Authenticated]
 */
final class AccountRename
{
    /**
     * Records the source and destination identities.
     */
    public function __construct(public readonly AccountName|CurrentAccount $from, public readonly AccountName|CurrentAccount $to)
    {
    }
}
