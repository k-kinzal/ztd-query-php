<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Text\Json;

use RuntimeException;

/**
 * A JSON operand fails at a byte boundary before it denotes an attribute document.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Failure extends RuntimeException
{
    /**
     * @param string $reason The syntax condition
     * @param int $position The byte position of the condition
     * @param bool $deep Whether the document exceeds the nesting limit
     */
    public function __construct(public readonly string $reason, public readonly int $position, public readonly bool $deep = false)
    {
        parent::__construct($reason, $position);
    }
}
