<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ChoiceDomain;
use SqlFaker\Grammar\Generation\Value\IntegerDomain;
use SqlFaker\Grammar\Generation\Value\SequenceDomain;
use SqlFaker\Grammar\Generation\Value\ValueChoices;
use SqlFaker\Grammar\Generation\Value\ValueDomain;

#[CoversClass(IntegerDomain::class)]
#[UsesClass(CharacterDomain::class)]
#[UsesClass(SequenceDomain::class)]
#[UsesClass(ChoiceDomain::class)]
#[UsesClass(ValueChoices::class)]
#[UsesClass(ValueDomain::class)]
final class IntegerDomainTest extends TestCase
{
    #[DataProvider('providerBounds')]
    public function testChooseReachesBothBoundsWithoutEnumeratingTheDomain(string $minimum, string $maximum): void
    {
        $domain = new IntegerDomain($minimum, $maximum);
        $visits = 0;
        $last = $domain->choose(static function (int $count) use (&$visits): int {
            ++$visits;
            return $count - 1;
        });
        self::assertSame($minimum, $domain->choose(static fn (int $count): int => 0));
        self::assertSame(str_repeat('0', 16) . $maximum, $last);
        self::assertLessThanOrEqual(strlen($maximum) + 2, $visits);
    }

    public function testChooseReachesAnInteriorValueAndCanForbidPadding(): void
    {
        $decisions = [1, 3, 2, 0];
        self::assertSame('42', (new IntegerDomain('0', '99', 0))->choose(static function (int $count) use (&$decisions): int {
            return array_shift($decisions) ?? 0;
        }));
    }


    /**
     * @return iterable<array{string, string}>
     */
    public static function providerBounds(): iterable
    {
        yield ['0', '0'];
        yield ['0', '2147483647'];
        yield ['2147483648', '9223372036854775807'];
        yield ['9223372036854775808', '18446744073709551615'];
        yield ['99', '101'];
    }
}
