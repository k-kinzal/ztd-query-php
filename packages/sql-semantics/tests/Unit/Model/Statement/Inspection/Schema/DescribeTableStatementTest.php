<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Schema\DescribeTableStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DescribeTableStatement::class)]
#[Medium]
final class DescribeTableStatementTest extends TestCase
{
    public function testWithOriginRetainsTableAndPattern(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind("DESCRIBE t 'i%'");
        self::assertInstanceOf(DescribeTableStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('i%', $copy->pattern);
        self::assertSame('DESCRIBE', $copy->kind->value);
        self::assertSame("DESCRIBE `t` 'i%'", $copy->toString());
    }

    public function testWithTableDescribesAnotherTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)'));
        $statement = $binder->bind('DESC t');
        $other = $binder->bind('DESC u');
        self::assertInstanceOf(DescribeTableStatement::class, $statement);
        self::assertInstanceOf(DescribeTableStatement::class, $other);
        $changed = $statement->withTable($other->table);
        self::assertSame('u', $changed->table->declaration->name);
        self::assertSame('t', $statement->table->declaration->name);
    }

    public function testWithPatternRestrictsOrWidensTheColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('DESC t id');
        self::assertInstanceOf(DescribeTableStatement::class, $statement);
        self::assertSame('DESCRIBE `t`', $statement->withPattern(null)->toString());
        self::assertSame("DESCRIBE `t` 'x_'", $statement->withPattern('x_')->toString());
        self::assertSame('id', $statement->pattern);
    }

    public function testResultColumnsAreTheShowColumnsFields(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('DESC t');
        self::assertInstanceOf(DescribeTableStatement::class, $statement);
        self::assertSame(['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'], array_column($statement->resultColumns(), 'name'));
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('DESC t');
        self::assertInstanceOf(DescribeTableStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new DescribeTableStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->table);
    }
}
