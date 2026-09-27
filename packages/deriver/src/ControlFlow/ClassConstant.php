<?php

declare(strict_types=1);

namespace Deriver\ControlFlow;

/**
 * Captures constant ownership, access, and target initializer type independently of its value.
 * @visibility root
 */
final class ClassConstant
{
    /**
     * @param string $className Declaring class after trait composition
     * @param string $name Case-sensitive constant or enum case name
     * @param string $visibility PHP access level
     * @param string $type Declared constant type or backed enum scalar type
     * @param bool $enum Whether the initializer constructs a singleton enum case
     */
    public function __construct(public readonly string $className, public readonly string $name, public readonly string $visibility = 'public', public readonly string $type = 'mixed', public readonly bool $enum = false)
    {
    }
}
