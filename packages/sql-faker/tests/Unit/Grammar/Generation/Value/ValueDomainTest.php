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

#[CoversClass(ValueDomain::class)]
#[UsesClass(CharacterDomain::class)]
#[UsesClass(IntegerDomain::class)]
#[UsesClass(SequenceDomain::class)]
#[UsesClass(ChoiceDomain::class)]
#[UsesClass(ValueChoices::class)]
final class ValueDomainTest extends TestCase
{
    public function testChooseUsesTheSameContractForLeavesAndComposites(): void
    {
        $leaf = new CharacterDomain(['a'], 1, 1);
        $composite = new SequenceDomain($leaf, new ChoiceDomain($leaf));
        self::assertSame('aa', $composite->choose(static fn (int $count): int => 0));
    }
}
