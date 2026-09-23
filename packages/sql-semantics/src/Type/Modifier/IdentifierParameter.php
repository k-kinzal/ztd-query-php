<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Modifier;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An unqualified identifier passed to a PostgreSQL type's modifier input function.
 * @visibility public
 * @example Keeping a modifier identifier separate from a row column
 *     (new \SqlSemantics\Type\Modifier\IdentifierParameter('currency'))->name // => 'currency'
 */
final class IdentifierParameter
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name)
    {
        if ($name === '' || str_contains($name, "\0")) {
            throw new InvalidStructure('A type-modifier identifier requires a nonempty name without a null byte.');
        }
    }
}
