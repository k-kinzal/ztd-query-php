<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionDefinition;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionDefinition::class)]
#[Medium]
final class PartitionDefinitionTest extends TestCase
{
    public function testReadsAPartitionWithoutValues(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY HASH (id) (PARTITION p0, PARTITION p1)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        $partitioning = $alteration->partitioning;
        self::assertSame(['p0', 'p1'], array_map(static fn (PartitionDefinition $partition): string => $partition->name, $partitioning->partitions));
        self::assertNull($partitioning->partitions[0]->values);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new PartitionDefinition('');
    }
}
