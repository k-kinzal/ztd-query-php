<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Binding;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A case-sensitive external binding key, independent of its native ordinal.
 *
 * @visibility public
 * @example Naming the binding for a positional native marker
 *     (new \SqlSemantics\Platform\MySql\Ir\Binding\ParameterName('quantity'))->value // => 'quantity'
 */
final class ParameterName
{
    use Snapshot;

    /**
     * Establishes the exact name without its marker delimiter.
     */
    public function __construct(public readonly string $value)
    {
        Check::input(preg_match('/\A[A-Za-z0-9_]+\z/D', $value) === 1, 'A parameter name requires ASCII letters, digits or underscores.');
    }
}
