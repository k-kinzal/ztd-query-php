<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\UnknownSymbolException;

#[CoversClass(UnknownSymbolException::class)]
final class UnknownSymbolExceptionTest extends TestCase
{
    public function testMessage(): void
    {
        $exception = new UnknownSymbolException('MY_SYMBOL');

        self::assertSame('Unknown symbol: MY_SYMBOL', $exception->getMessage());
    }
}
