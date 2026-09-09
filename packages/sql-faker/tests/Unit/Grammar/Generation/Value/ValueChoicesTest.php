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

#[CoversClass(ValueChoices::class)]
#[UsesClass(CharacterDomain::class)]
#[UsesClass(IntegerDomain::class)]
#[UsesClass(SequenceDomain::class)]
#[UsesClass(ChoiceDomain::class)]
#[UsesClass(ValueDomain::class)]
final class ValueChoicesTest extends TestCase
{
    public function testValueSamplesOncePerOccurrenceAndDefinition(): void
    {
        $calls = 0;
        $choices = new ValueChoices(static function (int $count) use (&$calls): int {
            ++$calls;
            return $count - 1;
        });
        $domain = new CharacterDomain(['x'], 1, 1);
        self::assertSame('x', $choices->value(0, 'name', $domain));
        $afterFirst = $calls;
        self::assertSame('x', $choices->value(0, 'name', $domain));
        self::assertSame($afterFirst, $calls);
        self::assertSame('x', $choices->value(1, 'name', $domain));
        self::assertGreaterThan($afterFirst, $calls);
        self::assertNull($choices->value(2, 'no-domain', null));
        self::assertNull((new ValueChoices(static fn (int $count): ?int => null))->value(0, 'empty-input', $domain));
    }

    public function testIndexPreservesTheCallerDecisionAndDefaultsExhaustedInput(): void
    {
        self::assertSame(2, (new ValueChoices(static fn (int $count): int => $count - 1))->index(3));
        self::assertSame(0, (new ValueChoices(static fn (int $count): ?int => null))->index(3));
    }
}
