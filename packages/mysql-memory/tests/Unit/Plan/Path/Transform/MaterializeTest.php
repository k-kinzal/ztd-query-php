<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Materialize;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Materialize::class)]
#[Small]
final class MaterializeTest extends TestCase
{
    public function testWidthIsTheNumberOfOutputColumnsOfTheQuery(): void
    {
        $materialize = new Materialize(new QueryPlan(new ZeroRows(3), [Domain::integer(), Domain::null()], ['a', 'b']));

        self::assertSame(2, $materialize->width());
        self::assertFalse($materialize->lateral);
    }
}
