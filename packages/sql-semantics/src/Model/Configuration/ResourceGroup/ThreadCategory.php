<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\ResourceGroup;

/**
 * Whether a resource group runs user threads or system threads; each type allows its own priority range.
 * @visibility public
 * @example Reading the priority range of a type
 *     [\SqlSemantics\Model\Configuration\ResourceGroup\ThreadCategory::User->lowestPriority(), \SqlSemantics\Model\Configuration\ResourceGroup\ThreadCategory::System->highestPriority()] // => [19, -20]
 */
enum ThreadCategory: string
{
    case User = 'USER';
    case System = 'SYSTEM';

    /**
     * Returns the most favorable thread priority the type allows; lower values are more favorable.
     */
    public function highestPriority(): int
    {
        return match ($this) {
            self::User => 0,
            self::System => -20,
        };
    }

    /**
     * Returns the least favorable thread priority the type allows.
     */
    public function lowestPriority(): int
    {
        return match ($this) {
            self::User => 19,
            self::System => 0,
        };
    }
}
