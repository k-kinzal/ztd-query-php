<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Maintenance\MySql\StatusColumn;
use SqlSemantics\Model\Statement\Maintenance\MySql\AnalyzeTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AnalyzeTablesStatement::class)]
#[Medium]
final class AnalyzeTablesStatementTest extends TestCase
{
    public function testResultColumnsExposeTypedServerOutputs(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('ANALYZE TABLE t');
        self::assertInstanceOf(AnalyzeTablesStatement::class, $statement);
        $columns = $statement->resultColumns();
        self::assertSame(['Table', 'Op', 'Msg_type', 'Msg_text'], array_column($columns, 'name'));
        self::assertInstanceOf(StatusColumn::class, $columns[0]->expression);
        self::assertSame($statement->scopeId, $columns[0]->expression->scopeId);
        self::assertSame('varchar', $columns[0]->expression->type->name);
    }

    public function testWithTablesResolvesNewTargetsAndRetainsTheOriginal(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('ANALYZE TABLE t');
        self::assertInstanceOf(AnalyzeTablesStatement::class, $statement);
        $other = $binder->bind('TRUNCATE TABLE u');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\TruncateTableStatement::class, $other);
        $changed = $statement->withTables([$other->table]);
        self::assertSame('ANALYZE TABLE `u`', $changed->toString());
        self::assertSame('t', $statement->tables[0]->declaration->name);
        self::assertSame('u', $changed->tables[0]->declaration->name);
        self::assertNotSame($statement, $changed);
    }

    public function testWithOriginPreservesTheOperation(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('ANALYZE TABLE t');
        self::assertInstanceOf(AnalyzeTablesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->tables, $copy->tables);
    }

    public function testWithBinlogChangesOnlyTheSelectedRequest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('ANALYZE TABLE t');
        self::assertInstanceOf(AnalyzeTablesStatement::class, $statement);
        $previous = $statement->binlog;
        $changed = $statement->withBinlog(BinlogPolicy::Omit);
        self::assertSame('ANALYZE NO_WRITE_TO_BINLOG TABLE `t`', $changed->toString());
        self::assertSame(BinlogPolicy::Omit, $changed->binlog);
        self::assertSame($previous, $statement->binlog);
    }
}
