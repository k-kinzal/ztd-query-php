<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Binding;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A native projected parameter with a resolved input type.
 *
 * @visibility public
 * @example Referring to the second bound position
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter(2))->toString() // => 'CAST($2 AS pg_catalog.int4)'
 */
final class Parameter
{
    use Snapshot;

    /**
     * Fixes a one-based position and its native input type.
     */
    public function __construct(
        public readonly int $position,
        public readonly CastDestination $input = CastDestination::Integer,
    ) {
        Check::input($position >= 1 && $position <= 1024, 'A parameter position must be between 1 and 1024.');
    }

    /**
     * Writes the native cast of the parameter.
     */
    public function toString(): string
    {
        return 'CAST($' . $this->position . ' AS pg_catalog.' . $this->input->value . ')';
    }
}
