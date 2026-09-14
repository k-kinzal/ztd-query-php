<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator\Exception;

use SqlFixture\Hydrator\HydrationException;

/**
 * Fixture data cannot supply a required constructor argument.
 */
final class MissingConstructorArgumentException extends HydrationException
{
    /**
     * Fixture data cannot supply a required constructor argument.
     */
    public function __construct(
        public readonly string $className,
        public readonly string $parameterName,
    ) {
        parent::__construct(sprintf(
            'Missing required constructor parameter "%s" for class "%s"',
            $parameterName,
            $className,
        ));
    }
}
