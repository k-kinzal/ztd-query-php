<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Role;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A PostgreSQL role identified by its case-sensitive name, distinct from symbolic principals.
 * @visibility public
 * @example Retaining an explicitly named role
 *     (new \SqlSemantics\Model\Configuration\Role\NamedRole('CURRENT_USER'))->name // => 'CURRENT_USER'
 */
final class NamedRole
{
    /**
     * PostgreSQL reserves lowercase public and none in role specifications.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name)
    {
        if (in_array($name, ['', 'public', 'none'], true)) {
            throw new InvalidStructure('A named role requires a nonempty name other than the reserved names public and none.');
        }
    }
}
