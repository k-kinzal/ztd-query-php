<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
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
        self::assertSame('SELECT CAST("a" AS integer), CAST("a" AS text) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
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
        self::assertSame('SELECT ("a" COLLATE "C") FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
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

    #[TestWith([Dialect::Sqlite, 'SELECT CAST(a AS) FROM t', \SqlSemantics\Model\Scalar\Operator\CastExpression::class, 'SELECT CAST("a" AS) FROM "main"."t"'])]
    #[TestWith([Dialect::Sqlite, 'SELECT cast(a as integer) FROM t', \SqlSemantics\Model\Scalar\Operator\CastExpression::class, 'SELECT CAST("a" AS "integer") FROM "main"."t"'])]
    #[TestWith([Dialect::Sqlite, 'SELECT a collate nocase FROM t', \SqlSemantics\Model\Scalar\Operator\CollatedExpression::class, 'SELECT ("a" COLLATE "nocase") FROM "main"."t"'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT CAST(a AS int) + 1 FROM t', \SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, 'SELECT (CAST("a" AS integer) + 1) FROM "public"."t"'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT cast(a as int) FROM t', \SqlSemantics\Model\Scalar\Operator\CastExpression::class, 'SELECT CAST("a" AS integer) FROM "public"."t"'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT a collate "C" FROM t', \SqlSemantics\Model\Scalar\Operator\CollatedExpression::class, 'SELECT ("a" COLLATE "C") FROM "public"."t"'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT a::text FROM t', \SqlSemantics\Model\Scalar\Operator\CastExpression::class, 'SELECT CAST("a" AS text) FROM "public"."t"'])]
    public function testCastAndCollationReadLowercaseKeywords(Dialect $dialect, string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(a TEXT)')))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame([$class, $expected], [$statement->outputs[0]->expression::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
