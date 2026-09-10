<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Value\QuotedDomain;

#[CoversClass(QuotedDomain::class)]
final class QuotedDomainTest extends TestCase
{
    public function testChooseEscapesDelimitersBackslashesAndUnicodeEscapeMarkersDuringConstruction(): void
    {
        self::assertSame("''''", (new QuotedDomain("'", minimum: 1, maximum: 1, alphabet: ["'"]))->choose(static fn (int $count): int => 0));
        self::assertSame("E'\\\\'", (new QuotedDomain("'", ['E'], true, 1, 1, ['\\']))->choose(static fn (int $count): int => 0));
        self::assertSame("U&'\\\\'", (new QuotedDomain("'", ['U&'], alphabet: ['\\'], minimum: 1, maximum: 1, unicodeEscapes: true))->choose(static fn (int $count): int => 0));
        self::assertSame('`a`', (new QuotedDomain('`', minimum: 1, maximum: 1, alphabet: ['a', ' '], trailingSpace: false))->choose(static fn (int $count): int => $count - 1));
    }

    public function testMatchStopsAtTheFirstUnescapedClosingDelimiter(): void
    {
        $domain = new QuotedDomain("'", backslash: true);
        self::assertSame([7], $domain->match("!'a''b' trailing", 1));
        self::assertSame([6], $domain->match("'a\\'b'"));
        self::assertSame([], $domain->match("'unclosed"));
        self::assertSame([], $domain->match("'a\0b'"));
        self::assertSame([], $domain->match("'a\\"));
        self::assertSame([], $domain->match("'a\\\0'"));
        self::assertSame([], (new QuotedDomain('"', minimum: 1))->match('""'));
        self::assertSame([3], (new QuotedDomain('[', closing: ']'))->match('[a]b]'));
        self::assertSame([], (new QuotedDomain("'", ['N']))->match("'text'"));
    }

    public function testEncodeReturnsCompleteEscapedAtoms(): void
    {
        $domain = new QuotedDomain("'", backslash: true);
        self::assertSame("''", $domain->encode("'"));
        self::assertSame('\\\\', $domain->encode('\\'));
        self::assertSame('猫', $domain->encode('猫'));
    }

    public function testEndReadsTheContentAfterAnOpeningDelimiter(): void
    {
        self::assertSame(5, (new QuotedDomain("'"))->end("text'", 1));
        self::assertNull((new QuotedDomain("'"))->end('unclosed', 0));
    }
}
