<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionDefinition;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\AddPartitions;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddPartitions::class)]
#[Medium]
final class AddPartitionsTest extends TestCase
{
    public function testReadsTheDefinitionsAndBinlogPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD PARTITION NO_WRITE_TO_BINLOG (PARTITION p VALUES IN (1))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddPartitions::class, $alteration);
        self::assertSame('p', $alteration->partitions[0]->name);
        self::assertSame(BinlogPolicy::Omit, $alteration->binlog);
    }

    public function testRejectsARepeatedName(): void
    {
        $this->expectException(InvalidStructure::class);
        new AddPartitions([new PartitionDefinition('p'), new PartitionDefinition('p')]);
    }
}
