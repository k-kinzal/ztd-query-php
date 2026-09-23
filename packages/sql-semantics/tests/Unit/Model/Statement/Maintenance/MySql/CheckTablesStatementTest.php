<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\CheckOption;
use SqlSemantics\Model\Maintenance\MySql\StatusColumn;
use SqlSemantics\Model\Statement\Maintenance\MySql\CheckTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CheckTablesStatement::class)]
#[Medium]
final class CheckTablesStatementTest extends TestCase
{
    public function testResultColumnsExposeTypedServerOutputs(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECK TABLE t QUICK');
        self::assertInstanceOf(CheckTablesStatement::class, $statement);
        $columns = $statement->resultColumns();
        self::assertSame(['Table', 'Op', 'Msg_type', 'Msg_text'], array_column($columns, 'name'));
        self::assertInstanceOf(StatusColumn::class, $columns[0]->expression);
        self::assertSame($statement->scopeId, $columns[0]->expression->scopeId);
        self::assertSame('varchar', $columns[0]->expression->type->name);
    }

    public function testWithTablesResolvesNewTargetsAndRetainsTheOriginal(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECK TABLE t QUICK');
        self::assertInstanceOf(CheckTablesStatement::class, $statement);
        $other = $binder->bind('TRUNCATE TABLE u');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\TruncateTableStatement::class, $other);
        $changed = $statement->withTables([$other->table]);
        self::assertSame('CHECK TABLE `u` QUICK', $changed->toString());
        self::assertSame('t', $statement->tables[0]->declaration->name);
        self::assertSame('u', $changed->tables[0]->declaration->name);
        self::assertNotSame($statement, $changed);
    }

    public function testWithOriginPreservesTheOperation(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECK TABLE t QUICK');
        self::assertInstanceOf(CheckTablesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->tables, $copy->tables);
    }

    public function testWithOptionsChangesOnlyTheSelectedRequest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECK TABLE t QUICK');
        self::assertInstanceOf(CheckTablesStatement::class, $statement);
        $previous = $statement->options;
        $changed = $statement->withOptions([CheckOption::Upgrade]);
        self::assertSame('CHECK TABLE `t` FOR UPGRADE', $changed->toString());
        self::assertSame([CheckOption::Upgrade], $changed->options);
        self::assertSame($previous, $statement->options);
    }
}
