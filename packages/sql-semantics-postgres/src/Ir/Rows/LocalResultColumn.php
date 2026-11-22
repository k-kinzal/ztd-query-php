<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A named result position addressing one slot of a local query.
 *
 * @visibility public
 * @example Reading the second position of a local source
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('count');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn(1, $name))->slot // => 1
 */
final class LocalResultColumn
{
    use Snapshot;

    /**
     * Establishes the local slot and the observed result name.
     */
    public function __construct(public readonly int $slot, public readonly Identifier $name)
    {
        Check::input($slot >= 0, 'A local result slot must be nonnegative.');
    }
}
