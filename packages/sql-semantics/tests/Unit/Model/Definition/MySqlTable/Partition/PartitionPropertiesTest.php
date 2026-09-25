<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionProperties;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionProperties::class)]
#[Medium]
final class PartitionPropertiesTest extends TestCase
{
    public function testReadsStorageOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY HASH (id) (PARTITION p DATA DIRECTORY = \'/d\' INDEX DIRECTORY = \'/i\' MIN_ROWS = 1 NODEGROUP = 2)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        $properties = $partitioning->partitions[0]->properties;
        self::assertSame(['/d', '/i', 1, 2], [$properties->dataDirectory, $properties->indexDirectory, $properties->minRows, $properties->nodeGroup]);
    }

    public function testRejectsANegativeNodeGroup(): void
    {
        $this->expectException(InvalidStructure::class);
        new PartitionProperties(nodeGroup: -1);
    }

    #[TestWith(['CREATE TABLE t(a INT) PARTITION BY HASH(a) (PARTITION p0 MAX_ROWS = 0 MIN_ROWS = 0)', \SqlSemantics\Model\Statement\CreateTableStatement::class, 'CREATE TABLE `t`(`a` integer) PARTITION BY HASH(`a`)(PARTITION `p0` MAX_ROWS = 0 MIN_ROWS = 0)'])]
    public function testNumbersAcceptZero(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
