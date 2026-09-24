<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Alteration;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;

/**
 * DISCARD OLD PASSWORD: drops the retained secondary credential.
 * @visibility public
 * @example Reading the discard request for the connecting client
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER USER USER() DISCARD OLD PASSWORD');
 *     $statement->alterations[0]->account // => \SqlSemantics\Model\Configuration\Account\ClientAccount::Connected
 */
final class OldPasswordDiscard
{
    /**
     * Names the account whose secondary credential is discarded.
     */
    public function __construct(public readonly AccountName|CurrentAccount|ClientAccount $account)
    {
    }
}
