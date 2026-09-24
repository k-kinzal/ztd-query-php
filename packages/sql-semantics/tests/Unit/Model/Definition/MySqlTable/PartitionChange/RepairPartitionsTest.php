<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\RepairPartitions;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Maintenance\MySql\RepairOption;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RepairPartitions::class)]
#[Medium]
final class RepairPartitionsTest extends TestCase
{
    public function testReadsTheRepairModes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t REPAIR PARTITION LOCAL p QUICK USE_FRM');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepairPartitions::class, $alteration);
        self::assertSame([RepairOption::Quick, RepairOption::UseDefinitionFile], $alteration->options);
        self::assertSame(BinlogPolicy::Omit, $alteration->binlog);
    }
}
