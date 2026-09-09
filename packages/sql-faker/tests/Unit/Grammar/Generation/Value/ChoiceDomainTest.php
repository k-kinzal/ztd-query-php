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

#[CoversClass(ChoiceDomain::class)]
#[UsesClass(CharacterDomain::class)]
#[UsesClass(IntegerDomain::class)]
#[UsesClass(SequenceDomain::class)]
#[UsesClass(ValueChoices::class)]
#[UsesClass(ValueDomain::class)]
final class ChoiceDomainTest extends TestCase
{
    public function testChooseReachesEachDeclaredForm(): void
    {
        $domain = new ChoiceDomain(new CharacterDomain(['0'], 1, 1, '0x'), new CharacterDomain(['f'], 2, 2, "X'", "'"));
        self::assertSame('0x0', $domain->choose(static fn (int $count): int => 0));
        self::assertSame("X'ff'", $domain->choose(static fn (int $count): int => $count - 1));
    }
}
