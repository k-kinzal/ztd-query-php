<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Replication;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW BINLOG EVENTS and SHOW RELAYLOG EVENTS.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Replication\LogEventField::LogName->label() // => 'Log_name'
 */
enum LogEventField: string implements MetadataField
{
    use TextField;

    case LogName = 'Log_name';
    case Position = 'Pos';
    case EventType = 'Event_type';
    case ServerId = 'Server_id';
    case EndPosition = 'End_log_pos';
    case Info = 'Info';

    /**
     * Positions and the server identity are integers.
     */
    public function type(): string
    {
        return match ($this) {
            self::Position, self::ServerId, self::EndPosition => 'bigint',
            self::LogName, self::EventType, self::Info => 'varchar',
        };
    }
}
