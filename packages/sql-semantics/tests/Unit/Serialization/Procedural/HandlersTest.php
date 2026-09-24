<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Cursor\Handler\CloseHandlerStatement;
use SqlSemantics\Model\Statement\Cursor\Handler\OpenHandlerStatement;
use SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerIndexStatement;
use SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerKeyStatement;
use SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Procedural\Handlers;

#[CoversClass(Handlers::class)]
#[Medium]
final class HandlersTest extends TestCase
{
    public function testWriteSpellsTheAliasAfterOpen(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('HANDLER t OPEN h');
        self::assertInstanceOf(OpenHandlerStatement::class, $statement);
        self::assertSame('HANDLER `t` OPEN AS `h`', Handlers::write($statement)->toString());
    }

    public function testWriteClosesTheHandler(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('HANDLER t CLOSE');
        self::assertInstanceOf(CloseHandlerStatement::class, $statement);
        self::assertSame('HANDLER `t` CLOSE', Handlers::write($statement)->toString());
    }

    public function testWriteReadsTheScanWithTheConditionAndTheWindow(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('HANDLER t READ FIRST WHERE a > 1 LIMIT 2, 3');
        self::assertInstanceOf(ReadHandlerStatement::class, $statement);
        self::assertSame('HANDLER `t` READ FIRST WHERE (`a` > 1) LIMIT 3 OFFSET 2', Handlers::write($statement)->toString());
    }

    public function testWriteReadsTheIndexStep(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY k(a))')))->bind('HANDLER t READ k PREV WHERE a = 1 LIMIT 5');
        self::assertInstanceOf(ReadHandlerIndexStatement::class, $statement);
        self::assertSame('HANDLER `t` READ `k` PREV WHERE (`a` = 1) LIMIT 5', Handlers::write($statement)->toString());
    }

    public function testWriteReadsTheIndexKey(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT, KEY k(a, b))')))->bind('HANDLER t READ k >= (1, 2)');
        self::assertInstanceOf(ReadHandlerKeyStatement::class, $statement);
        self::assertSame('HANDLER `t` READ `k` >= (1, 2)', Handlers::write($statement)->toString());
    }
}
