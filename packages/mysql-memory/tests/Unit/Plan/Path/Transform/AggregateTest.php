<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Aggregate;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;

#[CoversClass(Aggregate::class)]
#[Small]
final class AggregateTest extends TestCase
{
    public function testWidthIsTheWidthOfTheInputAndOneValuePerAggregate(): void
    {
        $count = new Accumulation(AggregateFunction::Count, [], false, Domain::integer());
        $aggregate = new Aggregate(new ZeroRows(3), [], [$count, $count]);

        self::assertSame(5, $aggregate->width());
        self::assertFalse($aggregate->rollup);
        self::assertSame([], $aggregate->rollupColumns);
    }
}
