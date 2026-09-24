<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Enumeration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabelPlacement;

#[CoversClass(EnumLabelPlacement::class)]
final class EnumLabelPlacementTest extends TestCase
{
    public function testSpellsTheSqlKeywords(): void
    {
        self::assertSame(['BEFORE', 'AFTER'], array_map(static fn (EnumLabelPlacement $placement): string => $placement->value, EnumLabelPlacement::cases()));
    }
}
