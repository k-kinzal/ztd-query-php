<?php

declare(strict_types=1);

namespace Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\GrammarException;
use SqlParser\Grammar\UnknownSymbolException;

#[CoversClass(GrammarException::class)]
#[UsesClass(UnknownSymbolException::class)]
#[Small]
final class GrammarExceptionTest extends TestCase
{
    public function testCarriesAMessage(): void
    {
        self::assertSame('Symbol x is declared twice', (new GrammarException('Symbol x is declared twice'))->getMessage());
        self::assertSame('Unknown grammar symbol: y', (new UnknownSymbolException('y'))->getMessage());
    }
}
