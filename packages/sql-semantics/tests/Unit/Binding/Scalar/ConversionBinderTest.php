<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\ConversionBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConversionBinder::class)]
#[Medium]
final class ConversionBinderTest extends TestCase
{
    public function testCastBindsBothCastSyntaxesAsExplicitConversions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TEXT)')))->bind('SELECT CAST(a AS INTEGER), a::text FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $cast = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\CastExpression::class, $cast);
        self::assertSame(\SqlSemantics\Model\Scalar\Operator\CastMode::Explicit, $cast->mode);
        self::assertSame('integer', $cast->type->name);
        self::assertSame('a', $cast->operand->columnBinding()?->column->name);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $cast->nullability);
        $shorthand = $statement->outputs[1]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\CastExpression::class, $shorthand);
        self::assertSame('text', $shorthand->type->name);
        self::assertSame('SELECT CAST("a" AS integer), CAST("a" AS text) FROM "public"."t"', $statement->toString());
    }

    public function testCastReadsSqliteTypeTokens(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a TEXT)')))->bind('SELECT CAST(a AS INTEGER) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $cast = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\CastExpression::class, $cast);
        self::assertSame('integer', $cast->type->name);
        self::assertSame(\SqlSemantics\Type\Identity\StorageAffinity::Integer, $cast->type->affinity);
    }

    public function testCollationKeepsTheOperandAndTheCollationName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TEXT)')))->bind('SELECT a COLLATE "C" FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $collated = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\CollatedExpression::class, $collated);
        self::assertSame(['C'], $collated->collation->parts);
        self::assertSame('a', $collated->operand->columnBinding()?->column->name);
        self::assertSame('text', $collated->type->name);
        self::assertSame('SELECT ("a" COLLATE "C") FROM "public"."t"', $statement->toString());
    }

    public function testCollationReadsUnquotedMySqlAndSqliteNames(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a TEXT)')))->bind('SELECT a COLLATE utf8mb4_bin FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $mysql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\CollatedExpression::class, $mysql->outputs[0]->expression);
        self::assertSame(['utf8mb4_bin'], $mysql->outputs[0]->expression->collation->parts);
        $sqlite = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a TEXT)')))->bind('SELECT a COLLATE NOCASE FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $sqlite);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\CollatedExpression::class, $sqlite->outputs[0]->expression);
        self::assertSame(['NOCASE'], $sqlite->outputs[0]->expression->collation->parts);
    }
}
