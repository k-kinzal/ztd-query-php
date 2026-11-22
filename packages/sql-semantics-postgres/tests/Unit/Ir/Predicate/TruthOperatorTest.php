<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator;

#[CoversClass(TruthOperator::class)]
#[Small]
final class TruthOperatorTest extends TestCase
{
    public function testEachPredicatePhraseHasAnExactEnumValue(): void
    {
        self::assertSame(
            ['IS TRUE', 'IS NOT TRUE', 'IS FALSE', 'IS NOT FALSE', 'IS UNKNOWN', 'IS NOT UNKNOWN'],
            array_column(TruthOperator::cases(), 'value'),
        );
    }
}
