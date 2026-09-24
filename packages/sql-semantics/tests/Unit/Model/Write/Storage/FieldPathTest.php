<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Insert\InsertValuesStatement;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Write\Assignment\ScalarAssignment;
use SqlSemantics\Model\Write\Storage\ColumnPath;
use SqlSemantics\Model\Write\Storage\ElementPath;
use SqlSemantics\Model\Write\Storage\FieldPath;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FieldPath::class)]
#[Medium]
final class FieldPathTest extends TestCase
{
    public function testColumnDelegatesToTheWritableBase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(r JSONB)')))->bind('UPDATE t SET r.f=1');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = $statement->writes[0]->target;
        self::assertInstanceOf(FieldPath::class, $path);
        self::assertInstanceOf(ColumnPath::class, $path->base);
        self::assertSame('f', $path->field);
        self::assertSame($path->base->column(), $path->column());
        self::assertSame('r', $path->column()->columnBinding()?->column->name);
        self::assertSame('UPDATE "public"."t" SET "r"."f" = 1', $statement->toString());
    }

    public function testTypeIsUnknownInTheBaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(r JSONB)')))->bind('UPDATE t SET r.f=1');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = $statement->writes[0]->target;
        self::assertInstanceOf(FieldPath::class, $path);
        self::assertSame('jsonb', $path->base->type()->name);
        self::assertSame('unknown', $path->type()->name);
        self::assertSame(Dialect::PostgreSql, $path->type()->dialect);
    }

    public function testNestsBeneathAnElementPath(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER[], r JSONB)')))->bind('INSERT INTO t(a[1], r.f) VALUES(1, 2)');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        $element = $statement->insertion->columns[0];
        self::assertInstanceOf(ElementPath::class, $element);
        self::assertInstanceOf(FieldPath::class, $statement->insertion->columns[1]);
        $nested = new FieldPath($element, 'g');
        self::assertSame($element, $nested->base);
        self::assertSame('a', $nested->column()->columnBinding()?->column->name);
        self::assertSame('unknown', $nested->type()->name);
    }
}
