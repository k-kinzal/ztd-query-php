<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Source;

/**
 * One option assignment of CHANGE REPLICATION SOURCE TO, typed by the kind of value the option takes.
 * @visibility public
 * @example Reading the option of a setting
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'db1'");
 *     $statement->settings[0]->option()->value // => 'SOURCE_HOST'
 */
interface SourceSetting
{
    /**
     * Names the option the setting assigns.
     */
    public function option(): SourceOption;
}
