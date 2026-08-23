<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\TablePartitionKey;
use ZtdQuery\Schema\TablePartitionStrategy;

#[CoversClass(TablePartitionKey::class)]
final class TablePartitionKeyTest extends TestCase
{
    public function testStoresStrategyAndExpressions(): void
    {
        $key = new TablePartitionKey(TablePartitionStrategy::Range, ['created_at', 'id']);

        self::assertSame(TablePartitionStrategy::Range, $key->strategy);
        self::assertSame(['created_at', 'id'], $key->expressions);
    }

    public function testRejectsEmptyExpression(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TablePartitionKey(TablePartitionStrategy::List, ['']);
    }

    public function testRejectsWhitespaceOnlyExpression(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TablePartitionKey(TablePartitionStrategy::List, ['  ']);
    }
}
