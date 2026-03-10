<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use Exception;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Grammar\UnknownSymbolException;

#[CoversNothing]
final class UnknownSymbolExceptionTest extends TestCase
{
    public function testMessage(): void
    {
        $exception = new UnknownSymbolException('MY_SYMBOL');

        self::assertSame('Unknown symbol: MY_SYMBOL', $exception->getMessage());
    }

    public function testExtendsException(): void
    {
        self::assertInstanceOf(Exception::class, new UnknownSymbolException('x'));
    }
}
