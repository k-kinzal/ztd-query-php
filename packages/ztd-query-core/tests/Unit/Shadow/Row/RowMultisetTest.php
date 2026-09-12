<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Row;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Row\RowMatch;
use ZtdQuery\Shadow\Row\RowMultiset;

#[CoversClass(RowMultiset::class)]
#[UsesClass(RowMatch::class)]
final class RowMultisetTest extends TestCase
{
    public function testDifferenceAnswersTheRowsNothingOnTheOtherSidePairsWith(): void
    {
        self::assertSame(
            [['id' => 2]],
            (new RowMultiset())->difference([['id' => 1], ['id' => 2]], [['id' => 1]]),
        );
    }

    public function testDifferencePairsOffARepeatedRowOnlyOnce(): void
    {
        self::assertSame(
            [['id' => 1]],
            (new RowMultiset())->difference([['id' => 1], ['id' => 1]], [['id' => 1]]),
        );
    }

    public function testDifferenceIsNothingWhereEveryRowIsPairedOff(): void
    {
        self::assertSame([], (new RowMultiset())->difference([['id' => 1]], [['id' => 1], ['id' => 2]]));
    }

    public function testDifferenceIgnoresTheOrderTheColumnsWereReadIn(): void
    {
        self::assertSame(
            [],
            (new RowMultiset())->difference([['id' => 1, 'name' => 'a']], [['name' => 'a', 'id' => 1]]),
        );
    }
}
