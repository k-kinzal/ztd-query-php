<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Prepared\CreateTableFromExecuteStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\Persistence;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(CreateTableFromExecuteStatement::class)]
#[Medium]
final class CreateTableFromExecuteStatementTest extends TestCase
{
    public function testKeepsTheTableAndThePreparedQueryThroughStructuralSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE UNLOGGED TABLE IF NOT EXISTS app.copied (a, b) WITH (fillfactor = 70) AS EXECUTE fetch_rows(1, 'x') WITH NO DATA", strict: false);
        self::assertInstanceOf(CreateTableFromExecuteStatement::class, $statement);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertSame(['app', 'copied'], $statement->name->parts);
        self::assertSame('fetch_rows', $statement->prepared);
        self::assertCount(2, $statement->arguments);
        self::assertSame(['a', 'b'], $statement->columns);
        self::assertInstanceOf(PostgreSqlProperties::class, $statement->properties);
        self::assertSame(Persistence::Unlogged, $statement->properties->persistence);
        self::assertTrue($statement->ifNotExists);
        self::assertFalse($statement->withData);
        $written = (new SimpleSerializer())->serialize($statement);
        self::assertSame('CREATE UNLOGGED TABLE IF NOT EXISTS "app"."copied"("a", "b") WITH ("fillfactor" = 70) AS EXECUTE "fetch_rows"(1, \'x\') WITH NO DATA', $written);
        $rebound = $binder->bind($written, strict: false);
        self::assertInstanceOf(CreateTableFromExecuteStatement::class, $rebound);
        self::assertSame($written, (new SimpleSerializer())->serialize($rebound));
    }

    public function testWithOriginKeepsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE copied AS EXECUTE fetch_rows', strict: false);
        self::assertInstanceOf(CreateTableFromExecuteStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::PostgreSql));
        self::assertSame('s9', $copy->scopeId);
        self::assertSame([$statement->name, 'fetch_rows', true], [$copy->name, $copy->prepared, $copy->withData]);
    }

    public function testWithPreparedReplacesTheQueryAndLeavesTheOriginalUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE copied AS EXECUTE fetch_rows(1)', strict: false);
        self::assertInstanceOf(CreateTableFromExecuteStatement::class, $statement);
        $changed = $statement->withPrepared('other_rows');
        self::assertNotSame($statement, $changed);
        self::assertSame('CREATE TABLE "copied" AS EXECUTE "other_rows"', $changed->toString());
        self::assertSame('fetch_rows', $statement->prepared);
    }

    public function testRejectsAnUnnamedPreparedQuery(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE copied AS EXECUTE fetch_rows', strict: false);
        self::assertInstanceOf(CreateTableFromExecuteStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateTableFromExecuteStatement($statement->origin, $statement->name, '');
    }

    public function testRejectsOtherDialects(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE copied AS EXECUTE fetch_rows', strict: false);
        self::assertInstanceOf(CreateTableFromExecuteStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateTableFromExecuteStatement($mysql->origin, $statement->name, 'fetch_rows');
    }
}
