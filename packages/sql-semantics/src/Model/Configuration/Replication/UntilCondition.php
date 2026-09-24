<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

/**
 * The point at which START REPLICA ... UNTIL stops the SQL thread.
 * @visibility public
 * @example Recognizing a stop point
 *     new \SqlSemantics\Model\Configuration\Replication\GapsClosed() instanceof \SqlSemantics\Model\Configuration\Replication\UntilCondition // => true
 */
interface UntilCondition
{
}
