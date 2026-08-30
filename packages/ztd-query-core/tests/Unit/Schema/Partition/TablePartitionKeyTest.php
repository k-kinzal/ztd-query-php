<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Schema\Partition\TablePartitionKey;
use ZtdQuery\Schema\Partition\TablePartitionStrategy;

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
        $this->expectException(InvalidDefinitionException::class);

        new TablePartitionKey(TablePartitionStrategy::List, ['']);
    }

    public function testRejectsWhitespaceOnlyExpression(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        new TablePartitionKey(TablePartitionStrategy::List, ['  ']);
    }
}
