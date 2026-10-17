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
     */
    public function __construct(public readonly int|float|string|null $value, public readonly Domain $domain, public readonly Collation $collation)
    {
    }
}
