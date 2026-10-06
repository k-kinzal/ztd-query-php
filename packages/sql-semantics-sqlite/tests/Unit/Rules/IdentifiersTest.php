<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Identifiers;

#[CoversClass(Identifiers::class)]
#[Small]
final class IdentifiersTest extends TestCase
{
    public function testDecodeKeepsABareWordAsWrittenWithItsCase(): void
    {
        self::assertSame('MyTable', (new Identifiers())->decode('MyTable'));
        self::assertSame('', (new Identifiers())->decode(''));
    }

    public function testDecodeReadsTheTextBetweenDoubleQuotesWithDoubledQuotesAsOne(): void
    {
        self::assertSame('a b', (new Identifiers())->decode('"a b"'));
        self::assertSame('say "hi"', (new Identifiers())->decode('"say ""hi"""'));
    }

    public function testDecodeReadsTheTextBetweenBackticksWithDoubledBackticksAsOne(): void
    {
        self::assertSame('a`b', (new Identifiers())->decode('`a``b`'));
    }

    public function testDecodeReadsTheTextBetweenSingleQuotesWithDoubledQuotesAsOne(): void
    {
        self::assertSame("it's", (new Identifiers())->decode("'it''s'"));
    }

    public function testDecodeReadsTheTextBetweenSquareBracketsWithoutUndoubling(): void
    {
        self::assertSame('a "b"', (new Identifiers())->decode('[a "b"]'));
        self::assertSame('x]]', (new Identifiers())->decode('[x]]]'));
    }
}
