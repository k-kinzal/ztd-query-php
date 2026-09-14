<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\ReturningProjection;

#[CoversClass(ReturningProjection::class)]
final class ReturningProjectionTest extends TestCase
{
    public function testItemsAndProjectReadEveryRowTheWayTheItemsWereWritten(): void
    {
        $projection = ReturningProjection::fromItems([
            ['source' => 'id', 'output' => 'original_id'],
            ['source' => null, 'output' => null],
            ['source' => 'name', 'output' => 'display_name'],
            ['source' => 'missing', 'output' => null],
        ]);

        self::assertSame([
            ['source' => 'id', 'output' => 'original_id'],
            ['source' => null, 'output' => null],
            ['source' => 'name', 'output' => 'display_name'],
            ['source' => 'missing', 'output' => null],
        ], $projection->items());

        self::assertSame([
            ['original_id' => 1, 'id' => 1, 'name' => 'Alice', 'display_name' => 'Alice', 'missing' => null],
            ['original_id' => 2, 'id' => 2, 'name' => 'Bob', 'display_name' => 'Bob', 'missing' => null],
        ], $projection->project([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]));
    }

    public function testProjectAnswersOneRowPerRowItWasGiven(): void
    {
        $projection = ReturningProjection::fromItems([['source' => 'id', 'output' => null]]);

        self::assertCount(2, $projection->project([['id' => 1], ['id' => 2]]));
    }
    public function testFromItemsRetainsTheRequestedOutputName(): void
    {
        $projection = ReturningProjection::fromItems([['source' => 'id', 'output' => 'key']]);

        self::assertSame([['key' => 7]], $projection->project([['id' => 7]]));
    }

}
