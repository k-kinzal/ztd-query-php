<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Write\Assignment\ScalarAssignment;
use SqlSemantics\Model\Write\Storage\ColumnPath;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ColumnPath::class)]
#[Medium]
final class ColumnPathTest extends TestCase
{
    public function testColumnReturnsTheReferenceItself(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('UPDATE t SET id=1');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = $statement->writes[0]->target;
        self::assertInstanceOf(ColumnPath::class, $path);
        self::assertSame($path->reference, $path->column());
        self::assertInstanceOf(ColumnReference::class, $path->reference);
        self::assertSame('id', $path->reference->binding->column->name);
        self::assertSame('UPDATE "public"."t" SET "id" = 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testTypeIsTheDeclaredColumnType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(name VARCHAR(10))')))->bind("UPDATE t SET name='x'");
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = $statement->writes[0]->target;
        self::assertInstanceOf(ColumnPath::class, $path);
        self::assertSame($path->reference->type, $path->type());
        self::assertSame('varchar', $path->type()->name);
        self::assertSame(Dialect::MySql, $path->type()->dialect);
    }

    public function testColumnMayBeUnresolvedForDiagnostics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('UPDATE t SET missing=1', strict: false);
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = $statement->writes[0]->target;
        self::assertInstanceOf(ColumnPath::class, $path);
        self::assertInstanceOf(UnresolvedColumnReference::class, $path->column());
        self::assertSame(['missing'], $path->column()->name);
        self::assertSame('unknown', $path->type()->name);
        self::assertSame('UPDATE "public"."t" SET "missing" = 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
