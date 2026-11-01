<?php

declare(strict_types=1);

namespace Tests\Unit\Hint;

use MySqlMemory\Hint\QueryBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryBlock::class)]
#[Small]
final class QueryBlockTest extends TestCase
{
    public function testLabelAnswersTheNameOrTheNumber(): void
    {
        $block = new QueryBlock(2);
        $unnamed = $block->label();
        $block->name = 'Qq';

        self::assertSame(['select#2', 'Qq'], [$unnamed, $block->label()]);
    }

    public function testTableComparesNamesAsWritten(): void
    {
        $block = new QueryBlock(1);
        $block->tables = [['t', ['PRIMARY', 'ka']], ['d', []]];

        self::assertSame([['PRIMARY', 'ka'], [], null], [$block->table('t'), $block->table('d'), $block->table('T')]);
    }
}
