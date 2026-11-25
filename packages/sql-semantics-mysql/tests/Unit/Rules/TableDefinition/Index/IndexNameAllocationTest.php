<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\IndexNameAllocation;

#[CoversClass(IndexNameAllocation::class)]
#[Small]
final class IndexNameAllocationTest extends TestCase
{
    public function testGeneratedUsesTheFirstAvailableSuffixWithinTheReleaseByteBoundary(): void
    {
        $prefix = str_repeat('x', 64);
        $taken = str_repeat('x', 30) . '_2';
        $used = [
            $prefix => new IndexDeclaration(new Name($prefix), PhysicalIndexKind::Index, true),
            $taken => new IndexDeclaration(new Name($taken), PhysicalIndexKind::Index, true),
        ];
        $allocation = new IndexNameAllocation();

        self::assertSame(str_repeat('x', 30) . '_3', $allocation->generated(new Name($prefix), $used, GrammarRelease::MySql5651)?->value);
        self::assertSame(str_repeat('x', 61) . '_2', $allocation->generated(new Name($prefix), $used, GrammarRelease::MySql910)?->value);
    }
}
