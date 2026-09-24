<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Source;

/**
 * ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = OFF or LOCAL; a UUID assignment is an AnonymousGtidUuid.
 * @visibility public
 * @example Reading the assignment
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = LOCAL');
 *     $statement->settings[0] // => \SqlSemantics\Model\Configuration\Replication\Source\AnonymousGtids::Local
 */
enum AnonymousGtids: string implements SourceSetting
{
    case Off = 'OFF';
    case Local = 'LOCAL';

    /**
     * Names the option the setting assigns.
     */
    public function option(): SourceOption
    {
        return SourceOption::AssignGtidsToAnonymousTransactions;
    }
}
