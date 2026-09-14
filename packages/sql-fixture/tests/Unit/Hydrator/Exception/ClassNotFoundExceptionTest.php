<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Hydrator\Exception\ClassNotFoundException::class)]
#[UsesClass(\SqlFixture\Hydrator\HydrationException::class)]
#[UsesClass(\SqlFixture\Hydrator\Exception\MissingConstructorArgumentException::class)]
final class ClassNotFoundExceptionTest extends TestCase
{
    public function testDescribesClassNotFound(): void
    {
        $exception = new \SqlFixture\Hydrator\Exception\ClassNotFoundException('NonExistentClass');
        self::assertSame('Class not found: NonExistentClass', $exception->getMessage());
        self::assertSame('NonExistentClass', $exception->className);
    }
}
