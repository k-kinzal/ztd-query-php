<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Cursor\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\Handler\KeyComparison;
use SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerKeyStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReadHandlerKeyStatement::class)]
#[Medium]
final class ReadHandlerKeyStatementTest extends TestCase
{
    public function testWithOriginRetainsTheLookup(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT, KEY k(a, b))')))->bind('HANDLER t READ k = (1, 2) WHERE b > 0 LIMIT 5');
        self::assertInstanceOf(ReadHandlerKeyStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('HANDLER `t` READ `k` = (1, 2) WHERE (`b` > 0) LIMIT 5', $copy->toString());
    }

    public function testWithIndexSearchesAnotherIndex(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY k(a), KEY j(a))')))->bind('HANDLER t READ k = (1)');
        self::assertInstanceOf(ReadHandlerKeyStatement::class, $statement);
        self::assertSame('j', $statement->withIndex('j')->index);
        self::assertSame('k', $statement->index);
    }

    public function testWithComparisonPositionsDifferently(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY k(a))')))->bind('HANDLER t READ k = (1)');
        self::assertInstanceOf(ReadHandlerKeyStatement::class, $statement);
        self::assertSame('HANDLER `t` READ `k` > (1)', $statement->withComparison(KeyComparison::After)->toString());
    }

    public function testWithKeyReplacesTheValues(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT, KEY k(a, b))'));
        $statement = $binder->bind('HANDLER t READ k = (1)');
        $other = $binder->bind('HANDLER t READ k = (3, 4)');
        self::assertInstanceOf(ReadHandlerKeyStatement::class, $statement);
        self::assertInstanceOf(ReadHandlerKeyStatement::class, $other);
        self::assertSame('HANDLER `t` READ `k` = (3, 4)', $statement->withKey($other->key)->toString());
    }

    public function testWithWhereRemovesTheCondition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY k(a))')))->bind('HANDLER t READ k = (1) WHERE a > 0');
        self::assertInstanceOf(ReadHandlerKeyStatement::class, $statement);
        self::assertSame('HANDLER `t` READ `k` = (1)', $statement->withWhere(null)->toString());
    }

    public function testWithLimitRemovesTheWindow(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY k(a))')))->bind('HANDLER t READ k = (1) LIMIT 9');
        self::assertInstanceOf(ReadHandlerKeyStatement::class, $statement);
        self::assertSame('HANDLER `t` READ `k` = (1)', $statement->withLimit(null)->toString());
    }

    public function testRejectsAnEmptyKey(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY k(a))')))->bind('HANDLER t READ k = (1)');
        self::assertInstanceOf(ReadHandlerKeyStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ReadHandlerKeyStatement($statement->origin, $statement->handler, 'k', KeyComparison::Equal, []);
    }
}
