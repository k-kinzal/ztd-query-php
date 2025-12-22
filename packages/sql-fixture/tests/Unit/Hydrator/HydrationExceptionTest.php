<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\HydrationException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HydrationException::class)]
final class HydrationExceptionTest extends TestCase
{
    #[Test]
    public function classNotFound(): void
    {
        $exception = HydrationException::classNotFound('NonExistentClass');
        self::assertSame('Class not found: NonExistentClass', $exception->getMessage());
    }

    #[Test]
    public function constructorParameterMissing(): void
    {
        $exception = HydrationException::constructorParameterMissing('User', 'name');
        self::assertSame('Missing required constructor parameter "name" for class "User"', $exception->getMessage());
    }

    #[Test]
    public function propertyNotAccessible(): void
    {
        $exception = HydrationException::propertyNotAccessible('User', 'password');
        self::assertSame('Property "password" is not accessible in class "User"', $exception->getMessage());
    }
}
