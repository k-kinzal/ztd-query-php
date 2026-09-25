<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Statement\Insert\InsertDefaultValuesStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InsertDefaultValuesStatement::class)]
#[Medium]
final class InsertDefaultValuesStatementTest extends TestCase
{
    public function testBindsWithoutRowsOrAQuery(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INTEGER DEFAULT 2)')))->bind('INSERT INTO t DEFAULT VALUES');
        self::assertInstanceOf(InsertDefaultValuesStatement::class, $statement);
        self::assertSame('t', $statement->insertion->target->declaration->name);
        self::assertSame(StatementKind::Insert, $statement->kind);
        self::assertSame([], $statement->outputs);
        self::assertFalse(property_exists($statement, 'rows'));
        self::assertFalse(property_exists($statement, 'query'));
        self::assertSame('INSERT INTO "main"."t" DEFAULT VALUES', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginPreservesTheDestination(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('INSERT INTO t DEFAULT VALUES RETURNING id');
        self::assertInstanceOf(InsertDefaultValuesStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::PostgreSql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->insertion, $copy->insertion);
        self::assertSame($statement->outputs, $copy->outputs);
        self::assertSame('INSERT INTO "public"."t" DEFAULT VALUES RETURNING "id" AS "id"', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithReturningAddsAndRemovesTheProjection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('INSERT INTO t DEFAULT VALUES');
        self::assertInstanceOf(InsertDefaultValuesStatement::class, $statement);
        $changed = $statement->withReturning([new OutputColumn(0, 'saved', Expression::reference(['id'], Dialect::PostgreSql))]);
        self::assertSame('saved', $changed->outputs[0]->name);
        self::assertSame('id', $changed->outputs[0]->expression->columnBinding()?->column->name);
        self::assertSame('INSERT INTO "public"."t" DEFAULT VALUES RETURNING "id" AS "saved"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame([], $changed->withReturning([])->outputs);
        self::assertSame([], $statement->outputs);
    }
}
