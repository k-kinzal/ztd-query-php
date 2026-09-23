<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\ChecksumColumn;
use SqlSemantics\Model\Maintenance\MySql\ChecksumMode;
use SqlSemantics\Model\Statement\Maintenance\MySql\ChecksumTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChecksumTablesStatement::class)]
#[Medium]
final class ChecksumTablesStatementTest extends TestCase
{
    public function testResultColumnsExposeTypedServerOutputs(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECKSUM TABLE t QUICK');
        self::assertInstanceOf(ChecksumTablesStatement::class, $statement);
        $columns = $statement->resultColumns();
        self::assertSame(['Table', 'Checksum'], array_column($columns, 'name'));
        self::assertInstanceOf(ChecksumColumn::class, $columns[0]->expression);
        self::assertSame($statement->scopeId, $columns[0]->expression->scopeId);
        self::assertSame('varchar', $columns[0]->expression->type->name);
    }

    public function testWithTablesResolvesNewTargetsAndRetainsTheOriginal(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECKSUM TABLE t QUICK');
        self::assertInstanceOf(ChecksumTablesStatement::class, $statement);
        $other = $binder->bind('TRUNCATE TABLE u');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\TruncateTableStatement::class, $other);
        $changed = $statement->withTables([$other->table]);
        self::assertSame('CHECKSUM TABLE `u` QUICK', $changed->toString());
        self::assertSame('t', $statement->tables[0]->declaration->name);
        self::assertSame('u', $changed->tables[0]->declaration->name);
        self::assertNotSame($statement, $changed);
    }

    public function testWithOriginPreservesTheOperation(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECKSUM TABLE t QUICK');
        self::assertInstanceOf(ChecksumTablesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->tables, $copy->tables);
    }

    public function testWithModeChangesOnlyTheSelectedRequest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECKSUM TABLE t QUICK');
        self::assertInstanceOf(ChecksumTablesStatement::class, $statement);
        $previous = $statement->mode;
        $changed = $statement->withMode(ChecksumMode::Scan);
        self::assertSame('CHECKSUM TABLE `t` EXTENDED', $changed->toString());
        self::assertSame(ChecksumMode::Scan, $changed->mode);
        self::assertSame($previous, $statement->mode);
    }
}
