<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A source log coordinate, SOURCE_LOG_FILE and SOURCE_LOG_POS (MASTER_LOG_FILE and MASTER_LOG_POS).
 * @visibility public
 * @example Reading the coordinate
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("START REPLICA UNTIL SOURCE_LOG_FILE = 'binlog.000002', SOURCE_LOG_POS = 4");
 *     $statement->until->position->text // => '4'
 */
final class SourcePosition implements UntilCondition
{
    /**
     * The file is a single-line string literal and the position an integer literal.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $file, public readonly Literal $position)
    {
        ReplicationText::check($file, 'A source log file name', true);
        ReplicationNumber::check($position, 'A source log position');
    }
}
