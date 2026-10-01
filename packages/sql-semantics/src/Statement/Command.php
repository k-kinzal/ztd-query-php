<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * A complete command or command sequence that can be the root of a Statement.
 *
 * @visibility public
 * @example Accepting a complete command
 *     $statement = static fn (\SqlSemantics\Statement\Command $command): \SqlSemantics\Statement\Statement => new \SqlSemantics\Statement\Statement($command);
 *     $statement instanceof \Closure // => true
 */
interface Command extends Element
{
}
