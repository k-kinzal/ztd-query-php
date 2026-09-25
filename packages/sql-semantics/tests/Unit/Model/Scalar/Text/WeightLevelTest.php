<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Text\WeightLevel;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(WeightLevel::class)]
#[Medium]
final class WeightLevelTest extends TestCase
{
    public function testKeepsASingleLevelWithModifiers(): void
    {
        $level = new WeightLevel(2, 2, true, true);
        self::assertSame([2, 2, true, true], [$level->first, $level->last, $level->descending, $level->reverse]);
    }

    #[TestWith([0, 1])]
    #[TestWith([1, 7])]
    #[TestWith([3, 2])]
    public function testRejectsLevelsOutsideOneToSix(int $first, int $last): void
    {
        $this->expectException(InvalidStructure::class);
        new WeightLevel($first, $last);
    }

    public function testRejectsModifiersOnARange(): void
    {
        $this->expectException(InvalidStructure::class);
        new WeightLevel(1, 3, true);
    }
}
