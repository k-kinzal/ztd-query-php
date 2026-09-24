<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Cursor\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\Handler\IndexStep;
use SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerIndexStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReadHandlerIndexStatement::class)]
#[Medium]
final class ReadHandlerIndexStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRead(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, KEY k(id))')))->bind('HANDLER t READ k NEXT LIMIT 3');
        self::assertInstanceOf(ReadHandlerIndexStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('HANDLER `t` READ `k` NEXT LIMIT 3', $copy->toString());
    }

    public function testWithIndexWalksAnotherIndex(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, KEY k(id))')))->bind('HANDLER t READ k NEXT');
        self::assertInstanceOf(ReadHandlerIndexStatement::class, $statement);
        self::assertSame('HANDLER `t` READ `PRIMARY` NEXT', $statement->withIndex('PRIMARY')->toString());
    }

    public function testWithStepChangesTheDirection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, KEY k(id))')))->bind('HANDLER t READ k NEXT');
        self::assertInstanceOf(ReadHandlerIndexStatement::class, $statement);
        self::assertSame(IndexStep::Previous, $statement->withStep(IndexStep::Previous)->step);
    }

    public function testWithWhereAddsACondition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, KEY k(id))'));
        $statement = $binder->bind('HANDLER t READ k NEXT');
        $filtered = $binder->bind('HANDLER t READ k FIRST WHERE id = 2');
        self::assertInstanceOf(ReadHandlerIndexStatement::class, $statement);
        self::assertInstanceOf(ReadHandlerIndexStatement::class, $filtered);
        self::assertSame('HANDLER `t` READ `k` NEXT WHERE (`id` = 2)', $statement->withWhere($filtered->where)->toString());
    }

    public function testWithLimitAddsAWindow(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, KEY k(id))'));
        $statement = $binder->bind('HANDLER t READ k NEXT');
        $limited = $binder->bind('HANDLER t READ k NEXT LIMIT 4');
        self::assertInstanceOf(ReadHandlerIndexStatement::class, $statement);
        self::assertInstanceOf(ReadHandlerIndexStatement::class, $limited);
        self::assertSame('HANDLER `t` READ `k` NEXT LIMIT 4', $statement->withLimit($limited->limit)->toString());
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, KEY k(id))')))->bind('HANDLER t READ k NEXT');
        self::assertInstanceOf(ReadHandlerIndexStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ReadHandlerIndexStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->handler, 'k', IndexStep::Next);
    }
}
