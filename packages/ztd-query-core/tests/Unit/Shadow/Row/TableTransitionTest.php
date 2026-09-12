<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Row;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Row\RowChange;
use ZtdQuery\Shadow\Row\TableTransition;

#[CoversClass(TableTransition::class)]
#[UsesClass(RowChange::class)]
final class TableTransitionTest extends TestCase
{
    public function testKeepsTheTableAndTheRowsThatMoved(): void
    {
        $change = new RowChange(['id' => 1], ['id' => 2]);
        $transition = new TableTransition('order', [['id' => 3]], [$change]);

        self::assertSame('order', $transition->table);
        self::assertSame([['id' => 3]], $transition->deleted);
        self::assertSame([$change], $transition->updated);
    }

    public function testIsEmptyWhenNothingWasDeletedAndNothingChanged(): void
    {
        self::assertTrue((new TableTransition('order', [], []))->isEmpty());
    }

    public function testIsNotEmptyWhenARowWasDeleted(): void
    {
        self::assertFalse((new TableTransition('order', [['id' => 1]], []))->isEmpty());
    }

    public function testIsNotEmptyWhenARowChanged(): void
    {
        $change = new RowChange(['id' => 1], ['id' => 2]);

        self::assertFalse((new TableTransition('order', [], [$change]))->isEmpty());
    }
}
