<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Text\WeightPadding;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(WeightPadding::class)]
#[Medium]
final class WeightPaddingTest extends TestCase
{
    public function testKeepsTheKindAndLength(): void
    {
        $padding = new WeightPadding(true, 8);
        self::assertSame([true, 8], [$padding->binary, $padding->length]);
    }

    public function testRejectsANonPositiveLength(): void
    {
        $this->expectException(InvalidStructure::class);
        new WeightPadding(false, 0);
    }
}
