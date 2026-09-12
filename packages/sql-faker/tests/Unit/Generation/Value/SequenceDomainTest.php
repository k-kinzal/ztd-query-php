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

#[CoversClass(SequenceDomain::class)]
#[UsesClass(CharacterDomain::class)]
#[UsesClass(IntegerDomain::class)]
#[UsesClass(ChoiceDomain::class)]
#[UsesClass(ValueChoices::class)]
#[UsesClass(ValueDomain::class)]
final class SequenceDomainTest extends TestCase
{
    public function testChooseConstructsAProductWithoutEnumeratingItsMembers(): void
    {
        $domain = new SequenceDomain(new IntegerDomain('0', '18446744073709551615', 0), new CharacterDomain(['.'], 1, 1), new CharacterDomain(['9'], 30, 30));
        $visits = 0;
        $value = $domain->choose(static function (int $count) use (&$visits): int {
            ++$visits;
            return $count - 1;
        });
        self::assertSame('18446744073709551615.' . str_repeat('9', 30), $value);
        self::assertLessThan(60, $visits);
        self::assertSame('', (new SequenceDomain())->choose(static fn (int $count): int => 0));
    }

    public function testMatchRetainsAmbiguousPrefixesUntilTheSuffixResolvesThem(): void
    {
        $domain = new SequenceDomain(new CharacterDomain(['a'], 1, 3), new CharacterDomain(['a'], 1, 1));
        self::assertSame([2, 3], $domain->match('aaa!'));
        self::assertSame([], $domain->match('a!'));
        self::assertSame([2], (new SequenceDomain())->match('xx', 2));
    }
}
