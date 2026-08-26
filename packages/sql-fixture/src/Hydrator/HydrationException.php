<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

use RuntimeException;
use Throwable;

/**
 * Reports that a fixture row could not be turned into the object asked for.
 *
 * A caller names a class and hands over a row; anything that stops those two
 * from meeting is reported here, so the caller learns that the class cannot be
 * hydrated rather than that reflection failed somewhere inside.
 */
final class HydrationException extends RuntimeException
{
    /**
     * Reports a class nothing can be loaded for.
     *
     * @param string $className Class the caller named
     *
     * @return self Exception naming the class
     */
    public static function classNotFound(string $className): self
    {
        return new self(sprintf('Class not found: %s', $className));
    }

    /**
     * Reports a class that exists but cannot be built.
     *
     * A class is not instantiable when it is abstract, when it is an enum,
     * when it is an internal class that refuses to be built without its
     * constructor, or when its constructor is not public. PHP raises an Error
     * for some of those, which is an engine failure rather than something to
     * catch, so those are asked about first and the reason is passed in.
     *
     * @param string $className Class the caller named
     * @param string $reason Why it cannot be built
     * @param Throwable|null $cause How PHP refused, where PHP is what refused
     *
     * @return self Exception naming the class and the reason, carrying the refusal
     */
    public static function notInstantiable(string $className, string $reason, ?Throwable $cause = null): self
    {
        return new self(sprintf('Cannot instantiate class "%s": %s', $className, $reason), 0, $cause);
    }

    /**
     * Reports a constructor parameter with nowhere to get a value.
     *
     * @param string $className Class being built
     * @param string $parameterName Parameter that could not be filled
     *
     * @return self Exception naming both
     */
    public static function parameterNotSupplied(string $className, string $parameterName): self
    {
        return new self(sprintf(
            'Missing required constructor parameter "%s" for class "%s"',
            $parameterName,
            $className,
        ));
    }

    /**
     * Reports a property that cannot be written to.
     *
     * @param string $className Class being built
     * @param string $propertyName Property that could not be assigned
     *
     * @return self Exception naming both
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
