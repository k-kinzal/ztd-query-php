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
use SqlSemantics\Model\Write\Storage\SlicePath;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\ArrayStorage;

#[CoversClass(SlicePath::class)]
#[Medium]
final class SlicePathTest extends TestCase
{
    public function testColumnDelegatesToTheWritableBase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER[])')))->bind('UPDATE t SET a[1:2]=a');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = $statement->writes[0]->target;
        self::assertInstanceOf(SlicePath::class, $path);
        self::assertInstanceOf(ColumnPath::class, $path->base);
        self::assertSame($path->base->column(), $path->column());
        self::assertSame('a', $path->column()->columnBinding()?->column->name);
        self::assertSame('1', $path->lower?->spelling());
        self::assertSame('2', $path->upper?->spelling());
        self::assertSame('UPDATE "public"."t" SET "a"[1 : 2] = "a"', $statement->toString());
    }

    public function testTypeIsTheWholeArrayType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER[])')))->bind('UPDATE t SET a[1:2]=a');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = $statement->writes[0]->target;
        self::assertInstanceOf(SlicePath::class, $path);
        self::assertSame($path->base->type(), $path->type());
        self::assertSame('integer[]', $path->type()->name);
        self::assertInstanceOf(ArrayStorage::class, $path->type()->identity);
        self::assertSame('integer', $path->type()->identity->element->name);
    }

    public function testAllowsOpenBounds(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER[])'));
        $statement = $binder->bind('UPDATE t SET a[:2]=a, a[1:]=a');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[1]);
        $head = $statement->writes[0]->target;
        $tail = $statement->writes[1]->target;
        self::assertInstanceOf(SlicePath::class, $head);
        self::assertInstanceOf(SlicePath::class, $tail);
        self::assertNull($head->lower);
        self::assertSame('2', $head->upper?->spelling());
        self::assertSame('1', $tail->lower?->spelling());
        self::assertNull($tail->upper);
        $expected = 'UPDATE "public"."t" SET "a"[: 2] = "a", "a"[1 :] = "a"';
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testRejectsABoundFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER[])')))->bind('UPDATE t SET a[1:2]=a');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $path = $statement->writes[0]->target;
        self::assertInstanceOf(SlicePath::class, $path);
        $this->expectException(InvalidStructure::class);
        new SlicePath($path->base, null, Expression::literal(2, Dialect::MySql));
    }
}
