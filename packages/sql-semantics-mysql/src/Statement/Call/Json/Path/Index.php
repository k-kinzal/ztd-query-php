<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json\Path;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * An array position counted from its first or last element.
 *
 * @visibility public
 * @example Refusing an offset outside the path grammar
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index(false, -1) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Index
{
    use Snapshot;

    /**
     * @param bool $last Whether the position counts back from the last element
     * @param int $offset The unsigned 32-bit distance from that end
     */
    public function __construct(public readonly bool $last, public readonly int $offset)
    {
        Check::input($offset >= 0 && $offset <= 4294967295, 'A JSON path index is an unsigned 32-bit distance.');
    }
}
