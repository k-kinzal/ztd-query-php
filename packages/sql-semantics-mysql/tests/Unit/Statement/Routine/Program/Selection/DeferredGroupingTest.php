<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Selection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

#[CoversClass(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredGrouping::class)]
#[Medium]
final class DeferredGroupingTest extends TestCase
{
    public function testRenderRetainsGroupingItemsAndTheModifier(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT a FROM t GROUP BY (a, 2), a WITH ROLLUP', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT a FROM t GROUP BY (a,2),a WITH ROLLUP')->toString());
    }

    public function testOrdinaryItemsRequireAnOrdinaryGrouping(): void
    {
        $this->expectExceptionMessage('A deferred grouping retains at least one deferred item.');
        new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredGrouping([new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral())]);
    }
}
