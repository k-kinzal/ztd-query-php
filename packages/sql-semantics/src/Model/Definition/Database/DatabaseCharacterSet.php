<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Selects the database's default character set by name or legacy server inheritance.
 * @visibility public
 * @example Inspecting a named default
 *     (new \SqlSemantics\Model\Definition\Database\DatabaseCharacterSet('utf8mb4'))->name // => 'utf8mb4'
 */
final class DatabaseCharacterSet
{
    /**
     * Requires an identifier; availability belongs to the supplied database environment.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string|ServerCharacterInheritance $name)
    {
        if ($name === '') {
            throw new InvalidStructure('A database character set requires a nonempty name or server inheritance.');
        }
    }
}
