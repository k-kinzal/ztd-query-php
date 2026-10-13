<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Snapshot;

/**
 * The input slot bound to a parameter occurrence, counted in written order from zero.
 *
 * The position is resolved from the statement structure, independently of source offsets,
 * derivation order and execution order. It remains known when no value has been supplied.
 *
 * @visibility public
 * @example Reading the slot of a parameter
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT ?');
 *     $operation->facts->scalar($operation->statement->items[0]->expression)->resolution->position // => 0
 */
final class ParameterBinding implements Resolution
{
    use Snapshot;

    /**
     * @param int $position The non-negative input slot
     */
    public function __construct(public readonly int $position)
    {
        Check::input($position >= 0, 'A parameter binding names a non-negative input slot.');
    }
}
