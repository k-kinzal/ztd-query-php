<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Filter;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Filter::class)]
#[Small]
final class FilterTest extends TestCase
{
    public function testWidthIsTheWidthOfTheInput(): void
    {
        self::assertSame(4, (new Filter(new ZeroRows(4), new ColumnRead(Domain::integer(), 0)))->width());
    }

    public function testWidthIgnoresThePrecondition(): void
    {
        $precondition = new ColumnRead(Domain::integer(), 1);
        $filter = new Filter(new ZeroRows(2), new ColumnRead(Domain::integer(), 0), $precondition);

        self::assertSame([2, $precondition, null], [$filter->width(), $filter->precondition, (new Filter(new ZeroRows(2), new ColumnRead(Domain::integer(), 0)))->precondition]);
    }
}
