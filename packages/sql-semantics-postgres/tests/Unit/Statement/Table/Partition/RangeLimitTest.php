<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\RangeLimit::class)]
#[Medium]
final class RangeLimitTest extends TestCase
{
    public function testMaximumIsTrueForMaxvalue(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES FROM (MINVALUE) TO (MAXVALUE)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf::class, $n2);
        $n3 = $n2->bound;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\RangeBound::class, $n3);
        $n4 = $n3->from[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\RangeLimit::class, $n4);
        $n5 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n5);
        $n6 = $n5->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf::class, $n6);
        $n7 = $n6->bound;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\RangeBound::class, $n7);
        $n8 = $n7->to[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\RangeLimit::class, $n8);
        self::assertSame([
          0 => false,
          1 => true,
        ], [$n4->maximum(), $n8->maximum()]);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES FROM (minvalue) TO (maxvalue)', []);
        self::assertSame('CREATE TABLE p1 PARTITION OF p FOR VALUES FROM (minvalue) TO (maxvalue)', $statement->toString());
    }

    public function testRefusesAnotherWord(): void
    {
        $this->expectExceptionMessage('An infinite range bound is minvalue or maxvalue.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\RangeLimit(new \SqlSemantics\Statement\Identifier\Name('MAXVALUE'));
    }
}
