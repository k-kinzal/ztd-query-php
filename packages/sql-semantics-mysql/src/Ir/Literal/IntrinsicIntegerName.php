<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Literal;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The full heading of an unaliased nonnegative integer result.
 *
 * @visibility public
 * @example Retaining the intrinsic name of a long padded literal
 *     $name = new \SqlSemantics\Platform\MySql\Ir\Literal\IntrinsicIntegerName(str_repeat('0', 256) . '1');
 *     strlen($name->value) // => 257
 */
final class IntrinsicIntegerName
{
    use Snapshot;

    /**
     * Retains a digit heading that the server cannot express as an ordinary alias.
     */
    public function __construct(public readonly string $value)
    {
        Check::input(
            strlen($value) > 256 && preg_match('/\A[0-9]+\z/', $value) === 1,
            'An intrinsic long integer name must consist of more than 256 digits.'
        );
    }

    /**
     * Returns the name's exact lookup key.
     */
    public function key(): string
    {
        return $this->value;
    }
}
