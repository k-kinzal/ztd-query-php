<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\RangeBound;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Definition\Relation\Partition\RangeBoundary;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RangeBound::class)]
#[Medium]
final class RangeBoundTest extends TestCase
{
    public function testReadsValuesAndMaxValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY RANGE COLUMNS (id, n) (PARTITION p VALUES LESS THAN (1, MAXVALUE))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        $bound = $partitioning->partitions[0]->values;
        self::assertInstanceOf(RangeBound::class, $bound);
        self::assertSame(RangeBoundary::MaxValue, $bound->bound[1]);
    }

    public function testRejectsMinValue(): void
    {
        $this->expectException(InvalidStructure::class);
        new RangeBound([RangeBoundary::MinValue]);
    }
}
