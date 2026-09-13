<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\HydrationException;

#[CoversClass(HydrationException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Exception\ClassNotFoundException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Exception\MissingConstructorArgumentException::class)]
final class HydrationExceptionTest extends TestCase
{
    #[Test]
    public function testClassNotFound(): void
    {
        $exception = new \SqlFixture\Hydrator\Exception\ClassNotFoundException('NonExistentClass');
        self::assertSame('Class not found: NonExistentClass', $exception->getMessage());
    }

    #[Test]
    public function testReportsMissingConstructorArgument(): void
    {
        $exception = new \SqlFixture\Hydrator\Exception\MissingConstructorArgumentException('User', 'name');
        self::assertSame('Missing required constructor parameter "name" for class "User"', $exception->getMessage());
    }

}
