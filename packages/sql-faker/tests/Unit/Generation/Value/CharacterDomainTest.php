<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Value\CharacterDomain;
use SqlFaker\Generation\Value\ChoiceDomain;
use SqlFaker\Generation\Value\IntegerDomain;
use SqlFaker\Generation\Value\SequenceDomain;
use SqlFaker\Generation\Value\ValueChoices;
use SqlFaker\Generation\Value\ValueDomain;

#[CoversClass(CharacterDomain::class)]
#[UsesClass(IntegerDomain::class)]
#[UsesClass(SequenceDomain::class)]
#[UsesClass(ChoiceDomain::class)]
#[UsesClass(ValueChoices::class)]
#[UsesClass(ValueDomain::class)]
final class CharacterDomainTest extends TestCase
{
    public function testChoosePreservesEncodedAtomsAndEvenDigitLengths(): void
    {
        $domain = new CharacterDomain(['0', 'f'], 0, 2, "X'", "'", 2);
        self::assertSame("X''", $domain->choose(static fn (int $count): int => 0));
        self::assertSame("X'ffff'", $domain->choose(static fn (int $count): int => $count - 1));
        self::assertSame("'a''b'", (new CharacterDomain(["a''b"], 1, 1, "'", "'"))->choose(static fn (int $count): int => 0));
    }

    public function testMatchKeepsAtomsDelimitersAndMultiplesTogether(): void
    {
        $domain = new CharacterDomain(['a', 'bb'], 0, 1, "'", "'", 2);
        self::assertSame([2], $domain->match("''"));
        self::assertSame([6], $domain->match("!'abb'!", 1));
        self::assertSame([], $domain->match("'a'"));
        self::assertSame([], $domain->match("'ab'"));
        self::assertSame([], $domain->match('abb'));
        self::assertSame([6], $domain->match("'aaaa'"));
    }

    public function testChooseSpansTheDefaultLengthsFromEmptyToTheScannerMaximum(): void
    {
        $domain = new CharacterDomain(['a']);
        self::assertSame('', $domain->choose(static fn (int $count): int => 0));
        self::assertSame(str_repeat('a', 255), $domain->choose(static fn (int $count): int => $count - 1));
    }

    public function testChooseStaysInsideDeclaredLengthBounds(): void
    {
        $domain = new CharacterDomain(['a'], 2, 4);
        self::assertSame('aa', $domain->choose(static fn (int $count): int => 0));
        self::assertSame('aaaa', $domain->choose(static fn (int $count): int => $count - 1));
    }

    public function testChooseAcceptsTheLargestScannerLength(): void
    {
        self::assertSame('', (new CharacterDomain(['a'], 0, 65535))->choose(static fn (int $count): int => 0));
    }

    public function testMatchRequiresTheDeclaredPrefix(): void
    {
        self::assertSame([], (new CharacterDomain(['a'], 0, 2, "X'", "'"))->match("'a'"));
    }

    public function testMatchCountsMultiplesFromTheMinimumLength(): void
    {
        self::assertSame([4, 6], (new CharacterDomain(['a'], 2, 3, multiple: 2))->match('aaaaaa'));
    }

    public function testMatchReportsEachEndOnceWhenAtomsOverlap(): void
    {
        self::assertSame([0, 1, 2, 3], (new CharacterDomain(['a', 'aa']))->match('aaa'));
    }
}
