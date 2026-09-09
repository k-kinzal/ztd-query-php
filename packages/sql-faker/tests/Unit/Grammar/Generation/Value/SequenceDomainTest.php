<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ChoiceDomain;
use SqlFaker\Grammar\Generation\Value\IntegerDomain;
use SqlFaker\Grammar\Generation\Value\SequenceDomain;
use SqlFaker\Grammar\Generation\Value\ValueChoices;
use SqlFaker\Grammar\Generation\Value\ValueDomain;

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
}
