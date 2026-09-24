<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Alteration;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;

/**
 * An account listed without any authentication change; the statement's shared clauses apply to it.
 * @visibility public
 * @example Reading an unchanged account
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER USER 'u'@'h' ACCOUNT LOCK");
 *     $statement->alterations[0]->account->host // => 'h'
 */
final class AccountTarget
{
    /**
     * Names the account only.
     */
    public function __construct(public readonly AccountName|CurrentAccount $account)
    {
    }
}
