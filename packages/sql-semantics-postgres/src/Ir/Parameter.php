<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A native projected parameter with a fixed signed integer cast.
 *
 * @visibility public
 * @example Referring to the second bound position
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Parameter(2))->toString() // => 'CAST($2 AS pg_catalog.int4)'
 */
final class Parameter
{
    use Snapshot;

    /**
     * Fixes a one-based position in the native profile.
     */
    public function __construct(public readonly int $position)
    {
        Check::input($position >= 1 && $position <= 1024, 'A parameter position must be between 1 and 1024.');
    }

    /**
     * Writes the native cast of the parameter.
     */
    public function toString(): string
    {
        return 'CAST($' . $this->position . ' AS pg_catalog.int4)';
    }
}
