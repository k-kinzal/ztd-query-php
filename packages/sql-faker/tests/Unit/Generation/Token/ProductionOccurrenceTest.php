<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;

#[CoversClass(ProductionOccurrence::class)]

final class ProductionOccurrenceTest extends TestCase
{
    public function testPreservesDistinctOccurrencesOfOneAlternative(): void
    {
        $first = new ProductionOccurrence(1, 0, 'expr', 2);
        $second = new ProductionOccurrence(4, 3, 'expr', 2);
        self::assertNotSame($first->id, $second->id);
        self::assertNotSame($first->parent, $second->parent);
        self::assertSame($first->rule, $second->rule);
        self::assertSame($first->ordinal, $second->ordinal);
    }
}
