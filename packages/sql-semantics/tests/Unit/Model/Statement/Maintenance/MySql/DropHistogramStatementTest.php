<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Statement\Maintenance\MySql\DropHistogramStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropHistogramStatement::class)]
#[Medium]
final class DropHistogramStatementTest extends TestCase
{
    public function testWithTargetChangesTableAndColumnIdentitiesTogether(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('ANALYZE TABLE t DROP HISTOGRAM ON id');
        self::assertInstanceOf(DropHistogramStatement::class, $statement);
        $other = $binder->bind(str_replace('TABLE t ', 'TABLE u ', 'ANALYZE TABLE t DROP HISTOGRAM ON id'));
        self::assertInstanceOf(DropHistogramStatement::class, $other);
        $changed = $statement->withTarget($other->table, $other->columns);
        self::assertSame('t', $statement->table->declaration->name);
        self::assertSame('u', $changed->table->declaration->name);
        $column = $changed->columns[0];
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $column);
        self::assertSame($changed->table->id, $column->binding->relationId);
        self::assertSame('u', $column->binding->table->name);
    }

    public function testWithTargetRejectsColumnsFromAnotherTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('ANALYZE TABLE t DROP HISTOGRAM ON id');
        self::assertInstanceOf(DropHistogramStatement::class, $statement);
        $other = $binder->bind('TRUNCATE TABLE u');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\TruncateTableStatement::class, $other);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withTarget($other->table, $statement->columns);
    }

    public function testWithOriginRetainsTheHistogramRequest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('ANALYZE TABLE t DROP HISTOGRAM ON id');
        self::assertInstanceOf(DropHistogramStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->table, $copy->table);
    }

    public function testWithBinlogChangesTheReplicationRequest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('ANALYZE TABLE t DROP HISTOGRAM ON id');
        self::assertInstanceOf(DropHistogramStatement::class, $statement);
        $changed = $statement->withBinlog(BinlogPolicy::Omit);
        self::assertSame(BinlogPolicy::Write, $statement->binlog);
        self::assertSame(BinlogPolicy::Omit, $changed->binlog);
        self::assertStringStartsWith('ANALYZE NO_WRITE_TO_BINLOG TABLE', $changed->toString());
    }

    public function testResultColumnsDescribeTheStatusWithoutEvaluatingIt(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('ANALYZE TABLE t DROP HISTOGRAM ON id');
        self::assertInstanceOf(DropHistogramStatement::class, $statement);
        self::assertSame(['Table', 'Op', 'Msg_type', 'Msg_text'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('varchar', $statement->resultColumns()[3]->expression->type->name);
    }
}
