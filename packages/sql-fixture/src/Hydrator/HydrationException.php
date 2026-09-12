<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

use RuntimeException;

/**
 * Hydration exception.
 */
final class HydrationException extends RuntimeException
{
    /**
     * Returns class not found.
     */
    public static function classNotFound(string $className): self
    {
        return new self(sprintf('Class not found: %s', $className));
    }

    /**
     * Returns constructor parameter missing.
     */
    public static function constructorParameterMissing(string $className, string $parameterName): self
    {
        return new self(sprintf(
            'Missing required constructor parameter "%s" for class "%s"',
            $parameterName,
            $className,
        ));
    }

    /**
     * Returns property not accessible.
     */
    public static function propertyNotAccessible(string $className, string $propertyName): self
    {
        return new self(sprintf(
            'Property "%s" is not accessible in class "%s"',
            $propertyName,
            $className,
        ));
    }
}
