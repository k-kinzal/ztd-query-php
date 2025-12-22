<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

#[CoversClass(ZtdPdoException::class)]
final class ZtdPdoExceptionTest extends TestCase
{
    public function testExtendsPdoException(): void
    {
        $exception = new ZtdPdoException('test');

        self::assertInstanceOf(PDOException::class, $exception);
    }

    public function testMessageIsSet(): void
    {
        $exception = new ZtdPdoException('something went wrong');

        self::assertSame('something went wrong', $exception->getMessage());
    }

    public function testPreviousExceptionIsPreserved(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new ZtdPdoException('wrapped', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    public function testDefaultCodeIsZero(): void
    {
        $exception = new ZtdPdoException('test');

        self::assertSame(0, $exception->getCode());
    }

    public function testExplicitCodeIsPreserved(): void
    {
        $exception = new ZtdPdoException('test', 42);

        self::assertSame(42, $exception->getCode());
    }
}
