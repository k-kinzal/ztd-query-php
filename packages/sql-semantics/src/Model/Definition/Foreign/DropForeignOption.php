<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes a named foreign-data wrapper option without supplying a value.
 * @visibility public
 * @example Inspecting a removal target
 *     (new \SqlSemantics\Model\Definition\Foreign\DropForeignOption('format'))->name // => 'format'
 */
final class DropForeignOption
{
    /**
     * A removal names the option and carries no replacement text.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name)
    {
        if ($name === '') {
            throw new InvalidStructure('A foreign option removal requires a name.');
        }
    }
}
