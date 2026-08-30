<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\CascadedChildren;
use ZtdQuery\Shadow\Row\RowChange;

#[CoversClass(CascadedChildren::class)]
#[UsesClass(RowChange::class)]
final class CascadedChildrenTest extends TestCase
{
    public function testRowsAnswersWhatTheCascadeWasGivenWhereItReachedNothing(): void
    {
        self::assertSame([['id' => 1]], (new CascadedChildren([['id' => 1]]))->rows());
    }

    public function testReplaceWritesTheRowAndRecordsWhatItWas(): void
    {
        $children = new CascadedChildren([['id' => 1], ['id' => 2]]);

        $children->replace(1, ['id' => 9]);

        self::assertSame(
            [[['id' => 1], ['id' => 9]], [[['id' => 2], ['id' => 9]]]],
            [
                $children->rows(),
                array_map(static fn (RowChange $c): array => [$c->before, $c->after], $children->updated()),
            ],
        );
    }

    public function testRemoveDropsThoseRowsAndKeepsTheRestInOrder(): void
    {
        $children = new CascadedChildren([['id' => 1], ['id' => 2], ['id' => 3]]);

        $children->remove([0, 2]);

        self::assertSame(
            [[['id' => 2]], [['id' => 1], ['id' => 3]]],
            [$children->rows(), $children->deleted()],
        );
    }

    public function testDeletedAnswersNothingWhereNoRowWent(): void
    {
        self::assertSame([], (new CascadedChildren([['id' => 1]]))->deleted());
    }

    public function testUpdatedAnswersNothingWhereNoRowChanged(): void
    {
        self::assertSame([], (new CascadedChildren([['id' => 1]]))->updated());
    }

    public function testAreUnchangedSaysWhetherTheCascadeReachedTheTableAtAll(): void
    {
        $reached = new CascadedChildren([['id' => 1]]);
        $reached->remove([0]);

        self::assertSame([true, false], [(new CascadedChildren([['id' => 1]]))->areUnchanged(), $reached->areUnchanged()]);
    }
}
