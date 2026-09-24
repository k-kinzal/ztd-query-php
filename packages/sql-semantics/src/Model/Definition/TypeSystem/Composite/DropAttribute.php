<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Composite;

use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops an attribute of a composite type, optionally tolerating its absence.
 * @visibility public
 * @example Dropping an attribute when it exists
 *     $change = new \SqlSemantics\Model\Definition\TypeSystem\Composite\DropAttribute('note', true);
 *     $change->ifExists // => true
 */
final class DropAttribute implements AttributeChange
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        TypeSystemInvariant::identifier($name);
    }
}
