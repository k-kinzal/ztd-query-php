<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Math\Intervals;
use MySqlMemory\Evaluation\Function\Math\Ranges;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;

#[CoversClass(Intervals::class)]
#[Small]
final class IntervalsTest extends TestCase
{
    public function testRoutinesNamesInterval(): void
    {
        self::assertSame(['INTERVAL'], array_map(static fn ($routine): string => $routine->name, (new Intervals())->routines()));
    }

    public function testResolveReadsEightKnownBoundsOnce(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $integer = Domain::of(Resolved::integer(), false);
        $bounds = array_map(static fn (int $bound): Constant => new Constant($integer, $bound), [1, 2, 3, 4, 5, 6, 7, 8]);
        $resolved = (new Intervals())->resolve($frame, [new Constant($integer, 5), ...$bounds], array_fill(0, 9, true));
        $kept = (new Intervals())->resolve($frame, [new Constant($integer, 5), ...array_slice($bounds, 0, 7)], array_fill(0, 8, true));

        self::assertCount(2, $resolved);
        self::assertInstanceOf(Ranges::class, $resolved[1]);
        self::assertTrue($resolved[1]->exact);
        self::assertCount(8, $kept);
    }

    public function testExactTellsIntegersDecimalsAndTemporalValues(): void
    {
        self::assertSame([true, true, false], [(new Intervals())->exact(Domain::of(Resolved::integer(), false)), (new Intervals())->exact(Domain::of(Resolved::decimal(2, 1), false)), (new Intervals())->exact(Domain::of(Resolved::double(), false))]);
    }

    public function testIntervalCountsTheBoundsReached(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT INTERVAL(23, 1, 15, 17, 30, 44, 200), INTERVAL(NULL, 1), INTERVAL(3, 1, NULL, 2), INTERVAL(5, 1, 1, 1, 9, 1, 1, 1, 1), INTERVAL(5, 'a', 'b')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['3', '-1', '3', '8', '2']], $result->rows);
        self::assertSame(2, $result->columns[0]->length);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'b'"]], $warnings->rows);
    }
}
