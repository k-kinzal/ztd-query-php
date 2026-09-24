<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Partition\RangePartitionBound::class)]
#[Medium]
final class RangePartitionBoundTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ATTACH PARTITION t_hi FOR VALUES FROM (100) TO (MAXVALUE)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Partition\AttachPartition::class, $statement->actions[0]);
        self::assertInstanceOf(Relation\Partition\RangePartitionBound::class, $statement->actions[0]->bound);
        self::assertSame([Relation\Partition\RangeBoundary::MaxValue], $statement->actions[0]->bound->to);
        self::assertSame('ALTER TABLE "t" ATTACH PARTITION "t_hi" FOR VALUES FROM(100) TO(MAXVALUE)', $statement->toString());
    }

    public function testRejectsBoundsOfDifferentWidths(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Partition\RangePartitionBound([\SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql)], [\SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql), \SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql)]);
    }
}
