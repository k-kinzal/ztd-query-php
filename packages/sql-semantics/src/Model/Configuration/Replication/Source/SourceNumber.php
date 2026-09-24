<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Source;

use SqlSemantics\Model\Configuration\Replication\ReplicationNumber;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A numeric option such as SOURCE_PORT = 3306, with the literal kept as written.
 * @visibility public
 * @example Reading a port
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO SOURCE_PORT = 3306');
 *     $statement->settings[0]->value->text // => '3306'
 */
final class SourceNumber implements SourceSetting
{
    /**
     * The largest SOURCE_DELAY, in seconds.
     */
    public const MAX_DELAY = 2147483647;

    /**
     * The largest SOURCE_HEARTBEAT_PERIOD, in seconds.
     */
    public const MAX_HEARTBEAT_PERIOD = 4294967;

    /**
     * Requires a numeric option and an unsigned MySQL number; SOURCE_DELAY and SOURCE_HEARTBEAT_PERIOD are range-checked.
     * @throws InvalidStructure
     */
    public function __construct(public readonly SourceOption $option, public readonly Literal $value)
    {
        if (!in_array($option->value, SourceOption::NUMBERS, true)) {
            throw new InvalidStructure($option->value . ' does not take a number.');
        }
        ReplicationNumber::check($value, $option->value);
        if ($option === SourceOption::Delay && ReplicationNumber::magnitude($value) > self::MAX_DELAY) {
            throw new InvalidStructure('SOURCE_DELAY is at most 2147483647 seconds.');
        }
        if ($option === SourceOption::HeartbeatPeriod && ReplicationNumber::real($value) > self::MAX_HEARTBEAT_PERIOD) {
            throw new InvalidStructure('SOURCE_HEARTBEAT_PERIOD is at most 4294967 seconds.');
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
