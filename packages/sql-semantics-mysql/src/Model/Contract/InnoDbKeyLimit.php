<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Contract;

/**
 * The measured per-field native InnoDB full-key capacity at a 16 KiB page size.
 * @visibility public
 * @example Reading the compact layout capacity
 *     \SqlSemantics\Platform\MySql\Model\Contract\InnoDbKeyLimit::Compact->value // => 'compact'
 */
enum InnoDbKeyLimit: string
{
    case Compact = 'compact';
    case LargePrefix = 'large-prefix';

    /**
     * Selects the stock default capacity for an exact native release.
     */
    public static function stock(GrammarRelease $release): self
    {
        return $release === GrammarRelease::MySql5651 ? self::Compact : self::LargePrefix;
    }

    /**
     * Gives the full per-field byte limit.
     */
    public function bytes(): int
    {
        return match ($this) {
            self::Compact => 767,
            self::LargePrefix => 3072,
        };
    }
}
