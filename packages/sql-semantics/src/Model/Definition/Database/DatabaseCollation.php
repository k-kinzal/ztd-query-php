<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Selects the database's default collation by name or legacy server inheritance.
 * @visibility public
 * @example Inspecting a named default
 *     (new \SqlSemantics\Model\Definition\Database\DatabaseCollation('utf8mb4_bin'))->name // => 'utf8mb4_bin'
 */
final class DatabaseCollation
{
    /**
     * Requires an identifier; availability belongs to the supplied database environment.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string|ServerCharacterInheritance $name)
    {
        if ($name === '') {
            throw new InvalidStructure('A database collation requires a nonempty name or server inheritance.');
        }
    }
}
