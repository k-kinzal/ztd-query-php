<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
    public function testParameterNotSupplied(): void
    {
        $exception = HydrationException::parameterNotSupplied('User', 'name');
        self::assertSame('Missing required constructor parameter "name" for class "User"', $exception->getMessage());
    }

    #[Test]
    public function testPropertyNotAccessible(): void
    {
        $exception = HydrationException::propertyNotAccessible('User', 'password');
        self::assertSame('Property "password" is not accessible in class "User"', $exception->getMessage());
    }

    #[Test]
    public function testNotInstantiableNamesTheClassAndWhyItCouldNotBeBuilt(): void
    {
        self::assertSame(
            'Cannot instantiate class "Order": it is abstract',
            HydrationException::notInstantiable('Order', 'it is abstract')->getMessage()
        );
    }

    #[Test]
    public function testNotInstantiableCarriesTheRefusalWhereSomethingElseRefused(): void
    {
        $cause = new RuntimeException('Class Generator is an internal class');

        $refusal = HydrationException::notInstantiable('Generator', $cause->getMessage(), $cause);

        self::assertSame($cause, $refusal->getPrevious());
    }

    #[Test]
    public function testNotInstantiableCarriesNothingWhereTheReasonWasAskedForRatherThanRaised(): void
    {
        self::assertNull(HydrationException::notInstantiable('Order', 'it is abstract')->getPrevious());
    }
}
