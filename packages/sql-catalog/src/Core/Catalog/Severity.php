<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Catalog;

/**
 * How much attention a finding deserves.
 *
 * @visibility root
 */
enum Severity: string
{
    case Info = 'info';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /**
     * The order severities sort in, highest first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
            self::Info => 0,
        };
    }

    /**
     * Whether this severity is at least as high as the given one.
     */
    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }
}
