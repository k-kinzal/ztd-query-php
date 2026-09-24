<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Enumeration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabelPlacement;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabelPosition;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(EnumLabelPosition::class)]
final class EnumLabelPositionTest extends TestCase
{
    public function testRetainsThePlacementAndNeighbor(): void
    {
        $position = new EnumLabelPosition(EnumLabelPlacement::Before, 'happy');
        self::assertSame(EnumLabelPlacement::Before, $position->placement);
        self::assertSame('happy', $position->neighbor);
    }

    public function testRejectsANeighborLongerThanALabel(): void
    {
        $this->expectException(InvalidStructure::class);
        new EnumLabelPosition(EnumLabelPlacement::After, str_repeat('a', 64));
    }
}
