<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Cursor\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\Handler\HandlerScan;
use SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReadHandlerStatement::class)]
#[Medium]
final class ReadHandlerStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRead(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t READ FIRST WHERE id > 1 LIMIT 2');
        self::assertInstanceOf(ReadHandlerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('HANDLER `t` READ FIRST WHERE (`id` > 1) LIMIT 2', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithScanContinuesTheScan(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t READ FIRST');
        self::assertInstanceOf(ReadHandlerStatement::class, $statement);
        self::assertSame('HANDLER `t` READ NEXT', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withScan(HandlerScan::Next)));
        self::assertSame(HandlerScan::First, $statement->scan);
    }

    public function testWithWhereRemovesTheCondition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t READ FIRST WHERE id > 1');
        self::assertInstanceOf(ReadHandlerStatement::class, $statement);
        self::assertNull($statement->withWhere(null)->where);
        self::assertNotNull($statement->where);
    }

    public function testWithLimitRemovesTheWindow(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t READ FIRST LIMIT 1, 2');
        self::assertInstanceOf(ReadHandlerStatement::class, $statement);
        self::assertSame('HANDLER `t` READ FIRST', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withLimit(null)));
        self::assertSame('1', $statement->limit?->offset?->spelling());
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t READ FIRST');
        self::assertInstanceOf(ReadHandlerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ReadHandlerStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->handler, HandlerScan::First);
    }
}
