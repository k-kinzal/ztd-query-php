<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\RadixDomain;
use SqlFaker\Grammar\Generation\Value\SequenceDomain;
use SqlFaker\Grammar\Generation\Value\WordDomain;

#[CoversClass(RadixDomain::class)]
#[UsesClass(CharacterDomain::class)]
#[UsesClass(WordDomain::class)]
#[UsesClass(SequenceDomain::class)]
final class RadixDomainTest extends TestCase
{
    public function testChooseConstructsEvenHexAndWholeAsciiBytes(): void
    {
        $ordinary = new RadixDomain('0123456789abcdefABCDEF', '0x', ['X', 'x'], 2, 32);
        self::assertSame('0x0', $ordinary->choose(static fn (int $count): int => 0));
        self::assertSame("X'" . str_repeat('F', 32) . "'", $ordinary->choose(static fn (int $count): int => $count - 1));
        $ascii = new RadixDomain('01', '0b', ['B', 'b'], 1, 64, true);
        self::assertSame('0b00000000', $ascii->choose(static fn (int $count): int => 0));
        self::assertSame("B'" . str_repeat('01111111', 8) . "'", $ascii->choose(static fn (int $count): int => $count - 1));
    }

    public function testMatchKeepsLexicalFormsIndependentOfSamplingBudgets(): void
    {
        $domain = new RadixDomain('0123456789abcdefABCDEF', '0x', ['X', 'x'], 2, 2, true);
        self::assertSame([3, 4], $domain->match('0xff!'));
        self::assertSame([5], $domain->match("x'ff'"));
        self::assertContains(8, $domain->match('0xabcdef'));
        self::assertSame([], $domain->match("X'f'"));
        self::assertSame([], $domain->match('0x'));
    }

    public function testDigitsDecodesOnlyCompleteMatchingForms(): void
    {
        $domain = new RadixDomain('0123456789abcdefABCDEF', '0x', ['X', 'x'], 2, 32);
        self::assertSame('0f', $domain->digits('0x0f'));
        self::assertSame('0f', $domain->digits("x'0f'"));
        self::assertSame('', $domain->digits("X''"));
        self::assertNull($domain->digits("X'0f'junk"));
        self::assertNull($domain->digits('0xfg'));
    }
}
