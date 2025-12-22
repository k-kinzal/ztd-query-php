<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;

#[CoversClass(ZtdMysqliException::class)]
final class ZtdMysqliExceptionTest extends TestCase
{
    public function testExtendsRuntimeException(): void
    {
        $exception = new ZtdMysqliException('test');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testMessageAndCode(): void
    {
        $exception = new ZtdMysqliException('Something went wrong', 42);

        self::assertSame('Something went wrong', $exception->getMessage());
        self::assertSame(42, $exception->getCode());
    }

    public function testPreviousException(): void
    {
        $previous = new \Exception('original');
        $exception = new ZtdMysqliException('wrapped', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    public function testDefaultCodeIsZero(): void
    {
        $exception = new ZtdMysqliException('test');

        self::assertSame(0, $exception->getCode());
    }

    public function testDefaultPreviousIsNull(): void
    {
        $exception = new ZtdMysqliException('test');

        self::assertNull($exception->getPrevious());
    }
}
