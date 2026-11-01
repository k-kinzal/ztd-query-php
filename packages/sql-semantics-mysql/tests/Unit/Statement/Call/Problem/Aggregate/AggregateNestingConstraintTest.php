<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraint;

#[CoversClass(AggregateNestingConstraint::class)]
#[Medium]
final class AggregateNestingConstraintTest extends TestCase
{
    public function testWindowNameDistinguishesWindowAndGroupCalls(): void
    {
        $count = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Count, []);
        self::assertNull((new AggregateNestingConstraint($count))->windowName());
        $count = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Count, [], over: new M\Call\Window\WindowSpec());
        self::assertSame('count', (new AggregateNestingConstraint($count))->windowName());
    }

    public function testMessageDescribesTheConflictingScopes(): void
    {
        $count = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Count, []);
        self::assertSame('An aggregate cannot evaluate this nested group or window call in its argument scope.', (new AggregateNestingConstraint($count))->message());
    }
}
