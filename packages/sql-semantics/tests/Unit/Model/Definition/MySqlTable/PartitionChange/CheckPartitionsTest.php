<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\PartitionChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\CheckPartitions;
use SqlSemantics\Model\Maintenance\IndexCache\AllPartitions;
use SqlSemantics\Model\Maintenance\MySql\CheckOption;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CheckPartitions::class)]
#[Medium]
final class CheckPartitionsTest extends TestCase
{
    public function testReadsTheChecks(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t CHECK PARTITION ALL FOR UPGRADE MEDIUM');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(CheckPartitions::class, $alteration);
        self::assertSame(AllPartitions::All, $alteration->partitions);
        self::assertSame([CheckOption::Upgrade, CheckOption::Medium], $alteration->options);
    }
}
