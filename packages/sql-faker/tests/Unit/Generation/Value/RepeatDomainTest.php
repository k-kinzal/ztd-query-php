<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Value\RepeatDomain;
use SqlFaker\Generation\Value\WordDomain;

#[CoversClass(RepeatDomain::class)]
#[UsesClass(WordDomain::class)]
final class RepeatDomainTest extends TestCase
{
    public function testChooseRepeatsCompleteComponents(): void
    {
        $domain = new RepeatDomain(new WordDomain(['_0']), 1, 3);
        self::assertSame('_0', $domain->choose(static fn (int $count): int => 0));
        self::assertSame('_0_0_0', $domain->choose(static fn (int $count): int => $count - 1));
    }

    public function testMatchRequiresProgressAndAllowsLongExplicitRuns(): void
    {
        $domain = new RepeatDomain(new WordDomain(['a', 'aa']), 1, 1);
        self::assertEqualsCanonicalizing([1, 2, 3], $domain->match('aaa'));
        self::assertSame([], $domain->match(''));
        self::assertSame([0], (new RepeatDomain(new WordDomain(['']), 0, 1))->match('abc'));
        self::assertSame([0], (new RepeatDomain(new WordDomain(['']), 2, 2))->match('abc'));
    }

}
