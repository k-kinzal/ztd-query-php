<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\Generation\Value\QuotedDomain;

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

    public function testChooseSpansTheDefaultBodyLengthsFromEmptyToTheScannerMaximum(): void
    {
        $domain = new QuotedDomain("'");
        self::assertSame("''", $domain->choose(static fn (int $count): int => 0));
        self::assertSame("'" . str_repeat('a', 255) . "'", $domain->choose(static fn (int $count): int => $count === 10 ? 0 : $count - 1));
    }

    public function testChooseKeepsATrailingSpaceUnlessTheScannerTrimsIt(): void
    {
        self::assertSame("' '", (new QuotedDomain("'", minimum: 1, maximum: 1))->choose(static fn (int $count): int => $count === 10 ? 3 : 0));
        self::assertSame("''''", (new QuotedDomain("'", minimum: 1, maximum: 1, trailingSpace: false))->choose(static fn (int $count): int => $count === 9 ? 3 : 0));
    }

    public function testChooseClosesWithTheDistinctClosingDelimiterWhenOneIsDeclared(): void
    {
        self::assertSame('[a]', (new QuotedDomain('[', minimum: 1, maximum: 1, closing: ']'))->choose(static fn (int $count): int => 0));
    }

    public function testEncodeLeavesBackslashesAloneUnlessTheScannerReadsEscapes(): void
    {
        self::assertSame('\\', (new QuotedDomain("'"))->encode('\\'));
        self::assertSame('\\\\', (new QuotedDomain("'", unicodeEscapes: true))->encode('\\'));
    }

    public function testMatchReadsEveryDeclaredPrefixInTurn(): void
    {
        $domain = new QuotedDomain("'", ['N', '']);
        self::assertSame([7], $domain->match("N'text'"));
        self::assertSame([6], $domain->match("'text'"));
        self::assertSame([], $domain->match("n'text'"));
    }

    public function testEndRequiresTheMinimumBodyLength(): void
    {
        $domain = new QuotedDomain("'", minimum: 1);
        self::assertSame(3, $domain->end("'a'", 1));
        self::assertNull($domain->end("''", 1));
    }
}
