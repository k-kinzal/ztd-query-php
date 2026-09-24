<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Source;

use SqlSemantics\Model\Configuration\Account\AccountName;

/**
 * PRIVILEGE_CHECKS_USER, the account whose privileges the applier checks, or NULL for no checks.
 * @visibility public
 * @example Reading the account
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO PRIVILEGE_CHECKS_USER = 'applier'@'localhost'");
 *     $statement->settings[0]->account?->host // => 'localhost'
 */
final class PrivilegeChecks implements SourceSetting
{
    /**
     * A null account is PRIVILEGE_CHECKS_USER = NULL, which turns privilege checks off.
     */
    public function __construct(public readonly ?AccountName $account)
    {
    }

    /**
     * Names the option the setting assigns.
     */
    public function option(): SourceOption
    {
        return SourceOption::PrivilegeChecksUser;
    }
}
