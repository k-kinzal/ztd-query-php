<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Source;

use SqlSemantics\Model\Configuration\Replication\ReplicationText;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = 'uuid', the UUID that GTIDs of anonymous transactions receive.
 * @visibility public
 * @example Reading the UUID
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = '3e11fa47-71ca-11e1-9e33-c80aa9429562'");
 *     $statement->settings[0]->uuid->text // => "'3e11fa47-71ca-11e1-9e33-c80aa9429562'"
 */
final class AnonymousGtidUuid implements SourceSetting
{
    /**
     * The literal is a quoted string whose first 36 characters are a UUID in its canonical text form, as MySQL reads it.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $uuid)
    {
        $text = ReplicationText::check($uuid, 'A UUID');
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $text) !== 1) {
            throw new InvalidStructure('ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS requires a UUID.');
        }
    }

    /**
     * Names the option the setting assigns.
     */
    public function option(): SourceOption
    {
        return SourceOption::AssignGtidsToAnonymousTransactions;
    }
}
