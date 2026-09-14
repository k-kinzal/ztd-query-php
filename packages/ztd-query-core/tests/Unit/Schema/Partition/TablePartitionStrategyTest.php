<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\Partition\TablePartitionStrategy;

#[CoversClass(TablePartitionStrategy::class)]
final class TablePartitionStrategyTest extends TestCase
{
    public function testValuesIdentifyStrategies(): void
    {
        self::assertSame('range', TablePartitionStrategy::Range->value);
        self::assertSame('list', TablePartitionStrategy::List->value);
        self::assertSame('hash', TablePartitionStrategy::Hash->value);
    }
}
