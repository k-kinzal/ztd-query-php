<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DeclareCursorStatementTest extends TestCase
{
    public function testWithOriginRetainsTheCursorRequest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DECLARE cur BINARY INSENSITIVE NO SCROLL CURSOR WITH HOLD FOR SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement::class, $statement);
        self::assertSame(\SqlSemantics\Model\Cursor\Scrollability::ForwardOnly, $statement->scroll);
        self::assertSame(\SqlSemantics\Model\Cursor\Sensitivity::Insensitive, $statement->sensitivity);
        self::assertTrue($statement->binary);
        self::assertTrue($statement->hold);
        self::assertInstanceOf(BoundSelect::class, $statement->query);
        self::assertSame('1', $statement->query->outputs[0]->expression->spelling());
        $changed = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $changed);
        self::assertSame($statement->toString(), $changed->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement::class, $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($changed)));
    }

    public function testBinaryAndHoldDefaultToOff(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DECLARE cur CURSOR FOR SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement::class, $statement);
        $rebuilt = new \SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement($statement->origin, $statement->name, $statement->query);
        self::assertSame([false, false], [$rebuilt->binary, $rebuilt->hold]);
    }

    public function testQueryMustUseThePostgreSqlStatementDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DECLARE cur CURSOR FOR SELECT 1');
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $this->expectExceptionMessage('A cursor query must use the statement dialect.');
        new \SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement($statement->origin, 'cur', $query);
    }

    public function testOriginMustBePostgreSql(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $this->expectExceptionMessage('This cursor command requires PostgreSQL.');
        new \SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement($query->origin, 'cur', $query);
    }
}
