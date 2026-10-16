<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Text\Json;

use RuntimeException;
use Throwable;

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
     * @param Throwable|null $previous The underlying decoded-operand failure
     */
    public function __construct(public readonly string $reason, public readonly int $position, public readonly bool $deep = false, ?Throwable $previous = null)
    {
        parent::__construct($reason, $position, $previous);
    }
}
