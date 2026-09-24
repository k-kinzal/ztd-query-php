<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(Relation\Partition\PartitionInvariant::class)]
#[Medium]
final class PartitionInvariantTest extends TestCase
{
    public function testExpressionsRequiresThePostgreSqlDialect(): void
    {
        Relation\Partition\PartitionInvariant::expressions([\SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql)]);
        $this->expectException(InvalidStructure::class);
        Relation\Partition\PartitionInvariant::expressions([\SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql), \SqlSemantics\Model\Expression::literal(1, Dialect::Sqlite)]);
    }
}
