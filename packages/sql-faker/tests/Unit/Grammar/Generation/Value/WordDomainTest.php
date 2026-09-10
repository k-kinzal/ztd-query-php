<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Value\WordDomain;

#[CoversClass(WordDomain::class)]
final class WordDomainTest extends TestCase
{
    public function testChooseSelectsLiteralWordsIncludingEmptyComponents(): void
    {
        $domain = new WordDomain(['', 'A.B', 'C~D'], true);
        self::assertSame('', $domain->choose(static fn (int $count): int => 0));
        self::assertSame('C~D', $domain->choose(static fn (int $count): int => $count - 1));
    }

    public function testMatchTreatsMetacharactersLiterallyAndPreservesOffsets(): void
    {
        self::assertSame([2, 5], (new WordDomain(['', 'A.B', 'A.B'], true))->match('xxa.b!', 2));
        self::assertSame([], (new WordDomain(['A.B']))->match('a.b'));
        self::assertSame([], (new WordDomain(['A.B'], true))->match('AxB'));
    }
}
