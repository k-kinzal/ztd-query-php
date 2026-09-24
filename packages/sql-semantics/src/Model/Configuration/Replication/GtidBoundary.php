<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * UNTIL SQL_BEFORE_GTIDS or SQL_AFTER_GTIDS with the GTID set as written.
 * @visibility public
 * @example Reading the boundary
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("START REPLICA SQL_THREAD UNTIL SQL_AFTER_GTIDS = '3E11FA47-71CA-11E1-9E33-C80AA9429562:1-5'");
 *     $statement->until->boundary->value // => 'SQL_AFTER_GTIDS'
 */
final class GtidBoundary implements UntilCondition
{
    /**
     * The GTID set is a quoted MySQL string literal; it is not parsed.
     * @throws InvalidStructure
     */
    public function __construct(public readonly GtidUntil $boundary, public readonly Literal $gtids)
    {
        ReplicationText::check($gtids, 'A GTID set');
    }
}
