<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine\Option\Volatility;

#[CoversClass(Volatility::class)]
#[Small]
final class VolatilityTest extends TestCase
{
    public function testSpellsEachVolatilityAsItsKeyword(): void
    {
        self::assertSame(['IMMUTABLE', 'STABLE', 'VOLATILE'], array_column(Volatility::cases(), 'value'));
    }
}
