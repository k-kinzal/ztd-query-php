<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Binding;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A native projected parameter with a fixed signed integer cast.
 *
 * @visibility public
 * @example Referring to the second bound position
 *     (new \SqlSemantics\Platform\MySql\Ir\Binding\Parameter(2))->toString() // => 'CAST(? AS SIGNED)'
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
        return 'CAST(? AS SIGNED)';
    }
}
