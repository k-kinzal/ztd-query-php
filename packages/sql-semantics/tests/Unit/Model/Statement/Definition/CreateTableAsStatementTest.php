<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Statement\Definition\CreateTableAsStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateTableAsStatement::class)]
#[Medium]
final class CreateTableAsStatementTest extends TestCase
{
    public function testBindsTheDeclaredColumnsAndDataPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE u(a) AS SELECT 1 WITH NO DATA');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        self::assertSame(['u'], $statement->name->parts);
        self::assertSame(['a'], $statement->columns);
        self::assertFalse($statement->withData);
        self::assertFalse($statement->ifNotExists);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertInstanceOf(BoundSelect::class, $statement->query);
        self::assertSame('CREATE TABLE "u"("a") AS SELECT 1 WITH NO DATA', $statement->toString());
    }

    public function testWithOriginPreservesTheQueryAndDeclaration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE TABLE u AS SELECT 1 AS id');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->query, $copy->query);
        self::assertSame($statement->name, $copy->name);
        self::assertTrue($copy->withData);
        self::assertSame('CREATE TABLE "u" AS SELECT 1 AS "id"', $copy->toString());
    }
}
