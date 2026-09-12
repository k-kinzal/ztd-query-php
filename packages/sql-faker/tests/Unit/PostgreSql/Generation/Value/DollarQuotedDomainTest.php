<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Value\CharacterDomain;
use SqlFaker\PostgreSql\Generation\Value\DollarQuotedDomain;

#[CoversClass(DollarQuotedDomain::class)]
#[UsesClass(CharacterDomain::class)]
final class DollarQuotedDomainTest extends TestCase
{
    public function testChooseReusesTheChosenTagWithoutASecondTagDecision(): void
    {
        $domain = new DollarQuotedDomain(new CharacterDomain(['a', 'b'], 1, 1), new CharacterDomain(['x'], 1, 1));
        self::assertSame('$b$x$b$', $domain->choose(static fn (int $count): int => $count - 1));
        self::assertSame('$$x$$', (new DollarQuotedDomain(new CharacterDomain(['a'], 0, 0), new CharacterDomain(['x'], 1, 1)))->choose(static fn (int $count): int => 0));
    }

    public function testMatchBindsTagsAndStopsAtTheFirstIdenticalDelimiter(): void
    {
        $domain = new DollarQuotedDomain(new CharacterDomain(['a'], 0, 3), new CharacterDomain(['x'], 0, 3));
        self::assertSame([7], $domain->match('$a$x$a$'));
        self::assertSame([5], $domain->match('$$x$$tail$$'));
        self::assertSame([], $domain->match('$a$x$b$'));
        self::assertSame([], $domain->match("$$\0$$"));
        self::assertSame([], $domain->match('plain'));
        self::assertSame([], $domain->match('$a'));
    }

    public function testMatchRequiresADollarAndALegalTagAtTheOffset(): void
    {
        $domain = new DollarQuotedDomain(new CharacterDomain(['a'], 0, 3), new CharacterDomain(['x'], 0, 3));
        self::assertSame([], $domain->match('x$a$x$a$'));
        self::assertSame([], $domain->match('$b$x$b$'));
        self::assertSame([8], $domain->match('!$a$x$a$', 1));
    }

    public function testMatchClosesAtTheDelimiterAfterTheTagAndOnlyRejectsNulBytesInsideTheBody(): void
    {
        $domain = new DollarQuotedDomain(new CharacterDomain(['a'], 0, 3), new CharacterDomain(['x'], 0, 3));
        self::assertSame([4], $domain->match('$$$$'));
        self::assertSame([5], $domain->match("\$\$x\$\$\0"));
        self::assertSame([11], $domain->match("\$aaa\$x\$aaa\$\0"));
    }
}
