<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Source;

/**
 * REQUIRE_TABLE_PRIMARY_KEY_CHECK, the primary key policy the applier enforces on replicated tables.
 * @visibility public
 * @example Reading the policy
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO REQUIRE_TABLE_PRIMARY_KEY_CHECK = GENERATE');
 *     $statement->settings[0] // => \SqlSemantics\Model\Configuration\Replication\Source\PrimaryKeyCheck::Generate
 */
enum PrimaryKeyCheck: string implements SourceSetting
{
    case Stream = 'STREAM';
    case On = 'ON';
    case Off = 'OFF';
    case Generate = 'GENERATE';

    /**
     * Names the option the setting assigns.
     */
    public function option(): SourceOption
    {
        return SourceOption::RequireTablePrimaryKeyCheck;
    }
}
