<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Operator\BinaryExpression;
use SqlSemantics\Model\Scalar\Value\ContextReference;
use SqlSemantics\Model\Scalar\Value\ContextValueKind;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\RowExpression;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\ValueExpressions;

#[CoversClass(ValueExpressions::class)]
#[Medium]
final class ValueExpressionsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'SELECT CURRENT_DATE, CURRENT_TIME(3), CURRENT_TIMESTAMP, LOCALTIME, CURRENT_USER, SESSION_USER, CURRENT_ROLE, CURRENT_SCHEMA, CURRENT_CATALOG', 'SELECT CURRENT_DATE, CURRENT_TIME(3), CURRENT_TIMESTAMP, LOCALTIME, CURRENT_USER, SESSION_USER, CURRENT_ROLE, CURRENT_SCHEMA, CURRENT_CATALOG'])]
    #[TestWith([Dialect::MySql, 'SELECT USER(), CURRENT_USER, CURRENT_ROLE(), SESSION_USER(), SYSTEM_USER()', 'SELECT USER(), CURRENT_USER, `CURRENT_ROLE`(), SESSION_USER(), SYSTEM_USER()'])]
    #[TestWith([Dialect::PostgreSql, "SELECT 1, 'a', TRUE, NULL, ROW(1,2), (1,2)", "SELECT 1, 'a', TRUE, NULL, ROW(1, 2), ROW(1, 2)"])]
    #[TestWith([Dialect::MySql, 'SELECT (1,2) = (3,4)', 'SELECT ((1, 2) = (3, 4))'])]
    #[TestWith([Dialect::MySql, "SELECT date '2020-01-01', timestamp '2020-01-01 00:00:00', time '10:00'", "SELECT DATE '2020-01-01', TIMESTAMP '2020-01-01 00:00:00', TIME '10:00'"])]
    #[TestWith([Dialect::PostgreSql, 'SET search_path TO public, pg_catalog', 'SET "search_path" = "public", "pg_catalog"'])]
    #[TestWith([Dialect::PostgreSql, 'SET TIME ZONE LOCAL', 'SET "timezone" = LOCAL'])]
    #[TestWith([Dialect::MySql, 'SET sql_mode = ON', 'SET `sql_mode` = ON'])]
    #[TestWith([Dialect::MySql, "SELECT _ascii X'2f', _UTF8MB4 0x0f, _binary b'01', N'x'", "SELECT _ascii X'2f', _utf8mb4 0x0f, _binary b'01', N'x'"])]
    public function testWriteKeepsLiteralContextRowAndConfigurationOperands(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    public function testWriteEmitsTheLiteralTextUnchanged(): void
    {
        $literal = Expression::literal("it's", Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $literal);
        self::assertSame("'it''s'", ValueExpressions::write($literal)->toString());
    }

    public function testWritePrefixesARowConstructorOnlyInPostgreSql(): void
    {
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (1, 2)');
        self::assertInstanceOf(BoundSelect::class, $postgres);
        $row = $postgres->outputs[0]->expression;
        self::assertInstanceOf(RowExpression::class, $row);
        self::assertSame('ROW(1, 2)', ValueExpressions::write($row)->toString());
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT (1, 2) = (3, 4)');
        self::assertInstanceOf(BoundSelect::class, $mysql);
        $comparison = $mysql->outputs[0]->expression;
        self::assertInstanceOf(BinaryExpression::class, $comparison);
        self::assertInstanceOf(RowExpression::class, $comparison->left);
        self::assertSame('(1, 2)', ValueExpressions::write($comparison->left)->toString());
    }

    public function testContextKeepsTheMySqlCallSpellingForUserRequests(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT USER(), CURRENT_USER');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $user = $statement->outputs[0]->expression;
        self::assertInstanceOf(ContextReference::class, $user);
        self::assertSame(ContextValueKind::User, $user->request);
        self::assertNull($user->precision);
        self::assertSame('USER()', ValueExpressions::context($user)->toString());
        $current = $statement->outputs[1]->expression;
        self::assertInstanceOf(ContextReference::class, $current);
        self::assertSame('CURRENT_USER', ValueExpressions::context($current)->toString());
    }

    public function testContextWritesAnExplicitPrecision(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT CURRENT_TIME(3), CURRENT_USER');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $time = $statement->outputs[0]->expression;
        self::assertInstanceOf(ContextReference::class, $time);
        self::assertSame(3, $time->precision);
        self::assertSame('CURRENT_TIME(3)', ValueExpressions::context($time)->toString());
        $user = $statement->outputs[1]->expression;
        self::assertInstanceOf(ContextReference::class, $user);
        self::assertSame('CURRENT_USER', ValueExpressions::context($user)->toString());
    }

    public function testWriteSpellsNestedArrayConstructorsWithTheArrayKeyword(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT ARRAY[[1, 2], [3, 4]]');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\ArrayConstructor::class, $value);
        self::assertSame('ARRAY[ARRAY[1, 2], ARRAY[3, 4]]', ValueExpressions::write($value)->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }
}
