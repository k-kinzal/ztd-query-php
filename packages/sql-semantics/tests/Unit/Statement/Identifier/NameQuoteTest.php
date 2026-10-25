<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\NameQuote;

#[CoversClass(NameQuote::class)]
#[Small]
final class NameQuoteTest extends TestCase
{
    public function testIdentifierDelimitersRemainDistinct(): void
    {
        self::assertSame(['Bare', 'Backtick', 'DoubleQuote'], array_map(static fn (NameQuote $quote): string => $quote->name, NameQuote::cases()));
    }
}
