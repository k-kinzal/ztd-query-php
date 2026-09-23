<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\MySql\RepairTablesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Execution\MySqlMaintenance;

#[CoversClass(MySqlMaintenance::class)]
#[Medium]
final class MySqlMaintenanceTest extends TestCase
{
    public function testWriteKeepsQuotedTargetsSeparateFromCommandKeywords(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE `check``table`(id INT)');
        $statement = (new Binder($schema))->bind('OPTIMIZE /* discarded */ LOCAL TABLE `check``table`');
        self::assertSame('OPTIMIZE NO_WRITE_TO_BINLOG TABLE `check``table`', MySqlMaintenance::write($statement)?->toString());
    }

    public function testTablesRetainsEveryRepairFlag(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('REPAIR TABLE t QUICK EXTENDED USE_FRM');
        self::assertInstanceOf(RepairTablesStatement::class, $statement);
        self::assertSame('REPAIR TABLE `t` QUICK EXTENDED USE_FRM', MySqlMaintenance::tables($statement)->toString());
    }

    public function testWriteReturnsNullForAnotherOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertNull(MySqlMaintenance::write($statement));
    }
}
