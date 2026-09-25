<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Cursor\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Cursor\Handler\CloseHandlerStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CloseHandlerStatement::class)]
#[Medium]
final class CloseHandlerStatementTest extends TestCase
{
    public function testWithOriginRetainsTheHandler(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t CLOSE');
        self::assertInstanceOf(CloseHandlerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('HANDLER `t` CLOSE', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithHandlerClosesAnotherHandler(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)'));
        $statement = $binder->bind('HANDLER t CLOSE');
        $other = $binder->bind('HANDLER u CLOSE');
        self::assertInstanceOf(CloseHandlerStatement::class, $statement);
        self::assertInstanceOf(CloseHandlerStatement::class, $other);
        self::assertSame('HANDLER `u` CLOSE', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withHandler($other->handler)));
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t CLOSE');
        self::assertInstanceOf(CloseHandlerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CloseHandlerStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->handler);
    }
}
