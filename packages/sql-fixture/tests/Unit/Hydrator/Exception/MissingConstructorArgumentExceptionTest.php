<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Hydrator\Exception\MissingConstructorArgumentException::class)]
#[UsesClass(\SqlFixture\Hydrator\HydrationException::class)]
#[UsesClass(\SqlFixture\Hydrator\Exception\ClassNotFoundException::class)]
final class MissingConstructorArgumentExceptionTest extends TestCase
{
    public function testDescribesMissingConstructorArgument(): void
    {
        $exception = new \SqlFixture\Hydrator\Exception\MissingConstructorArgumentException('User', 'name');
        self::assertSame('Missing required constructor parameter "name" for class "User"', $exception->getMessage());
        self::assertSame('User', $exception->className);
        self::assertSame('name', $exception->parameterName);
    }
}
