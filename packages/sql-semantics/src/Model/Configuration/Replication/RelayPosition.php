<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A relay log coordinate, RELAY_LOG_FILE and RELAY_LOG_POS.
 * @visibility public
 * @example Reading the coordinate
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("START REPLICA UNTIL RELAY_LOG_FILE = 'relay.000002', RELAY_LOG_POS = 4");
 *     $statement->until->position->text // => '4'
 */
final class RelayPosition implements UntilCondition
{
    /**
     * The file is a single-line string literal and the position an integer literal.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $file, public readonly Literal $position)
    {
        ReplicationText::check($file, 'A relay log file name', true);
        ReplicationNumber::check($position, 'A relay log position');
    }
}
