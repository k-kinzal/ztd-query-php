<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Partition\PartitionKey;
use SqlSemantics\Schema\Partition\PartitionScheme;
use SqlSemantics\Schema\Partition\PartitionStrategy;

#[CoversClass(PartitionScheme::class)]
#[Medium]
final class PartitionSchemeTest extends TestCase
{
    public function testRetainsTheStrategyAndOrderedKeys(): void
    {
        $keys = [new PartitionKey(Expression::literal(1, Dialect::PostgreSql)), new PartitionKey(Expression::literal(2, Dialect::PostgreSql))];
        $scheme = new PartitionScheme(PartitionStrategy::Range, $keys);
        self::assertSame(PartitionStrategy::Range, $scheme->strategy);
        self::assertSame($keys, $scheme->keys);
    }

    public function testRejectsListPartitioningOnSeveralKeys(): void
    {
        $this->expectException(InvalidStructure::class);
        new PartitionScheme(PartitionStrategy::List, [new PartitionKey(Expression::literal(1, Dialect::PostgreSql)), new PartitionKey(Expression::literal(2, Dialect::PostgreSql))]);
    }
}
