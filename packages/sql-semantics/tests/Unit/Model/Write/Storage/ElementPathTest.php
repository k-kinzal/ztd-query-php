<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment\ScalarAssignment;
use SqlSemantics\Model\Write\Storage\ColumnPath;
use SqlSemantics\Model\Write\Storage\ElementPath;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ElementPath::class)]
#[Medium]
final class ElementPathTest extends TestCase
{
    public function testColumnDelegatesToTheWritableBase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER[])')))->bind('UPDATE t SET a[1]=2');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = $statement->writes[0]->target;
        self::assertInstanceOf(ElementPath::class, $path);
        self::assertInstanceOf(ColumnPath::class, $path->base);
        self::assertSame($path->base->column(), $path->column());
        self::assertSame('a', $path->column()->columnBinding()?->column->name);
        self::assertSame('1', $path->index->spelling());
        self::assertSame('UPDATE "public"."t" SET "a"[1] = 2', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testTypeIsTheArrayElementType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER[])')))->bind('UPDATE t SET a[1]=2');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = $statement->writes[0]->target;
        self::assertInstanceOf(ElementPath::class, $path);
        self::assertSame('integer[]', $path->base->type()->name);
        self::assertSame('integer', $path->type()->name);
    }

    public function testTypeIsUnknownBeneathANonArrayBase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('UPDATE t SET id=1');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = new ElementPath($statement->writes[0]->target, Expression::literal(1, Dialect::PostgreSql));
        self::assertSame('unknown', $path->type()->name);
        self::assertSame(Dialect::PostgreSql, $path->type()->dialect);
    }

    public function testRejectsAnIndexFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER[])')))->bind('UPDATE t SET a[1]=2');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = $statement->writes[0]->target;
        self::assertInstanceOf(ElementPath::class, $path);
        $this->expectException(InvalidStructure::class);
        new ElementPath($path->base, Expression::literal(1, Dialect::MySql));
    }
}
