<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Partition\PartitionStrategy;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionStrategy::class)]
#[Medium]
final class PartitionStrategyTest extends TestCase
{
    public function testRepresentsEveryStrategyWithItsKeyword(): void
    {
        self::assertSame(['RANGE', 'LIST', 'HASH'], array_column(PartitionStrategy::cases(), 'value'));
    }

    public function testClassifiesTheStrategyNameCaseInsensitively(): void
    {
        $properties = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER) PARTITION BY "list" (id)')->tables[0]->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $properties);
        self::assertSame(PartitionStrategy::List, $properties->partitioning?->strategy);
    }
}
