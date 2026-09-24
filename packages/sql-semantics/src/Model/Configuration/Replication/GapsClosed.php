<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

/**
 * UNTIL SQL_AFTER_MTS_GAPS: stop once a multithreaded applier has no gaps left in the relay log.
 * @visibility public
 * @example Creating the condition
 *     new \SqlSemantics\Model\Configuration\Replication\GapsClosed() instanceof \SqlSemantics\Model\Configuration\Replication\UntilCondition // => true
 */
final class GapsClosed implements UntilCondition
{
}
