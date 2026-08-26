<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlFixture\Hydrator\HydrationException;

#[CoversClass(HydrationException::class)]
final class HydrationExceptionTest extends TestCase
{
    #[Test]
    public function testClassNotFound(): void
    {
        $exception = HydrationException::classNotFound('NonExistentClass');
        self::assertSame('Class not found: NonExistentClass', $exception->getMessage());
    }

    #[Test]
    public function testConstructorParameterMissing(): void
    {
        $exception = HydrationException::constructorParameterMissing('User', 'name');
        self::assertSame('Missing required constructor parameter "name" for class "User"', $exception->getMessage());
    }

    #[Test]
    public function testPropertyNotAccessible(): void
    {
        $exception = HydrationException::propertyNotAccessible('User', 'password');
        self::assertSame('Property "password" is not accessible in class "User"', $exception->getMessage());
    }

    #[Test]
    public function testNotInstantiableNamesTheClassAndCarriesWhatReflectionRefused(): void
    {
        $cause = new ReflectionException('Class is abstract');

        $exception = HydrationException::notInstantiable('Order', $cause);

        self::assertSame('Cannot instantiate class "Order": Class is abstract', $exception->getMessage());
        self::assertSame($cause, $exception->getPrevious());
    }
}
