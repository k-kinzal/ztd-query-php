<?php

declare(strict_types=1);

namespace Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\UnknownSymbolException;

#[CoversClass(UnknownSymbolException::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class UnknownSymbolExceptionTest extends TestCase
{
    public function testMessageNamesTheSymbol(): void
    {
        $exception = new UnknownSymbolException('expr_list');

        self::assertSame('expr_list', $exception->symbol);
        self::assertSame('Unknown grammar symbol: expr_list', $exception->getMessage());
    }
}
