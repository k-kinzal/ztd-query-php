<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator\Exception;

use SqlFixture\Hydrator\HydrationException;

/**
 * The requested hydration class does not exist.
 */
final class ClassNotFoundException extends HydrationException
{
    /**
     * The requested hydration class does not exist.
     */
    public function __construct(
        public readonly string $className,
    ) {
        parent::__construct(sprintf('Class not found: %s', $className));
    }
}
