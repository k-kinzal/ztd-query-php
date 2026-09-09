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

}
