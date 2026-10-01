<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Type;

/**
 * No type exists for an invalid reference. This is not uncertainty.
 * @example Reading semantic relationships
 *     $type = new \SqlSemantics\Semantic\Type\InvalidReference('missing-column');
 *     $type->name // => 'invalid'
 *
 * @visibility public
 */
final class InvalidReference
{
    /**
     * The semantic name exposed by this value.
     */
    public readonly string $name;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly string $diagnostic)
    {
        $this->name = 'invalid';
    }
}
