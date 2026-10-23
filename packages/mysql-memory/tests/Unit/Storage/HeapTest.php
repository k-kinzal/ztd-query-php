<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Storage\Heap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Heap::class)]
#[Small]
final class HeapTest extends TestCase
{
    public function testInsertNumbersRowsInOrder(): void
    {
        $heap = new Heap();

        $first = $heap->insert([1, 'a']);
        $second = $heap->insert([2, 'b']);

        self::assertSame([1, 2, [1 => [1, 'a'], 2 => [2, 'b']], 3], [$first, $second, $heap->rows, $heap->nextRow]);
    }

    public function testInsertNeverReusesTheNumberOfADeletedRow(): void
    {
        $heap = new Heap();
        $heap->insert([1]);
        $heap->delete(1);

        self::assertSame([2, [2 => [2]]], [$heap->insert([2]), $heap->rows]);
    }

    public function testUpdateReplacesTheValuesOfARow(): void
    {
        $heap = new Heap();
        $heap->insert([1, 'a']);

        $heap->update(1, [1, 'z']);

        self::assertSame([1 => [1, 'z']], $heap->rows);
    }

    public function testDeleteRemovesARow(): void
    {
        $heap = new Heap();
        $heap->insert([1]);
        $heap->insert([2]);

        $heap->delete(1);

        self::assertSame([2 => [2]], $heap->rows);
    }

    public function testCopyIsNotReachedByLaterChanges(): void
    {
        $heap = new Heap([], 1, 5);
        $heap->insert([1]);
        $copy = $heap->copy();

        $heap->insert([2]);
        $heap->autoIncrement = 9;

        self::assertSame([[1 => [1]], 2, 5], [$copy->rows, $copy->nextRow, $copy->autoIncrement]);
    }
}
