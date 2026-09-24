<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Administration\FlushTablesForExportStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FlushTablesForExportStatement::class)]
#[Medium]
final class FlushTablesForExportStatementTest extends TestCase
{
    public function testWithOriginPreservesTablesAndPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('FLUSH TABLES t FOR EXPORT');
        self::assertInstanceOf(FlushTablesForExportStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->tables, $copy->tables);
        self::assertSame('FLUSH TABLES `t` FOR EXPORT', $copy->toString());
    }

    public function testWithTablesReplacesTheTargetsImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)'));
        $statement = $binder->bind('FLUSH TABLES t FOR EXPORT');
        $other = $binder->bind('FLUSH TABLES u FOR EXPORT');
        self::assertInstanceOf(FlushTablesForExportStatement::class, $statement);
        self::assertInstanceOf(FlushTablesForExportStatement::class, $other);
        $changed = $statement->withTables($other->tables);
        self::assertSame('u', $changed->tables[0]->declaration->name);
        self::assertSame('t', $statement->tables[0]->declaration->name);
    }

    public function testWithBinlogChangesThePolicyImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('FLUSH TABLES t FOR EXPORT');
        self::assertInstanceOf(FlushTablesForExportStatement::class, $statement);
        self::assertSame('FLUSH NO_WRITE_TO_BINLOG TABLES `t` FOR EXPORT', $statement->withBinlog(BinlogPolicy::Omit)->toString());
        self::assertSame(BinlogPolicy::Write, $statement->binlog);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('FLUSH TABLES t FOR EXPORT');
        self::assertInstanceOf(FlushTablesForExportStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new FlushTablesForExportStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->tables);
    }

    public function testRejectsAnEmptyTableList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('FLUSH TABLES t FOR EXPORT');
        $this->expectException(InvalidStructure::class);
        new FlushTablesForExportStatement($statement->origin, []);
    }
}
