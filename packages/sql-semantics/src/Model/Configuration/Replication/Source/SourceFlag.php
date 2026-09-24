<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Source;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An option that switches a behavior on or off, such as SOURCE_SSL = 1; MySQL reads any nonzero number as on.
 * @visibility public
 * @example Reading a switch
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO SOURCE_AUTO_POSITION = 1');
 *     $statement->settings[0]->enabled // => true
 */
final class SourceFlag implements SourceSetting
{
    /**
     * Requires an option that takes on or off.
     * @throws InvalidStructure
     */
    public function __construct(public readonly SourceOption $option, public readonly bool $enabled)
    {
        if (!in_array($option->value, SourceOption::FLAGS, true)) {
            throw new InvalidStructure($option->value . ' is not switched on or off.');
        }
    }

    /**
     * Names the option the setting assigns.
     */
    public function option(): SourceOption
    {
        return $this->option;
    }
}
