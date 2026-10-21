<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * An immutable user-variable value, type and assignment collation.
 *
 * Keeping them together lets a failed SET restore the complete previous entry.
 * The assignment collation is separate from a numeric value's binary collation.
 *
 * @visibility MySqlMemory
 */
final class UserEntry
{
    /**
     * @param int|float|string|null $value The stored value
     * @param Domain $domain The value's domain
     * @param Collation $collation The collation retained at assignment
     * @param bool|null $binaryString The binary-string interpretation retained across a NULL assignment, independently of the current value domain
     * @param bool $assigned Whether an assignment has executed, rather than only allocating its name
     */
    public function __construct(public readonly int|float|string|null $value, public readonly Domain $domain, public readonly Collation $collation, public readonly ?bool $binaryString = null, public readonly bool $assigned = true)
    {
    }
}
