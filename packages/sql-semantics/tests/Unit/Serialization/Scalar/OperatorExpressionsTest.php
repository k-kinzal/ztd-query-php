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
use SqlSemantics\Model\Scalar\Operator\BinaryExpression;
use SqlSemantics\Model\Scalar\Operator\CastExpression;
use SqlSemantics\Model\Scalar\Operator\CastMode;
use SqlSemantics\Model\Scalar\Operator\CollatedExpression;
use SqlSemantics\Model\Scalar\Operator\UnaryExpression;
use SqlSemantics\Model\Statement\CompoundStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\OperatorExpressions;

#[CoversClass(OperatorExpressions::class)]
#[Medium]
final class OperatorExpressionsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, "SELECT -1, NOT TRUE, 1 IS NULL, 'a' COLLATE \"C\", CAST(1 AS TEXT), 1::text, 2 IS DISTINCT FROM 3", "SELECT (- 1), (NOT TRUE), (1 IS NULL), ('a' COLLATE \"C\"), CAST(1 AS text), CAST(1 AS text), (2 IS DISTINCT FROM 3)"])]
    #[TestWith([Dialect::MySql, 'SELECT 1 DIV 2, id COLLATE utf8mb4_bin, 1 <=> NULL FROM t', 'SELECT (1 DIV 2), (`id` COLLATE `utf8mb4_bin`), (1 <=> NULL) FROM `t`'])]
    #[TestWith([Dialect::Sqlite, "SELECT 'a' || 'b', ~1, 1 << 2", "SELECT ('a' || 'b'), (~ 1), (1 << 2)"])]
    public function testWriteParenthesizesEveryOperationToPreservePrecedence(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testWritePlacesAPostfixOperatorAfterItsOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 IS NULL, NOT TRUE');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $postfix = $statement->outputs[0]->expression;
        self::assertInstanceOf(UnaryExpression::class, $postfix);
        self::assertTrue($postfix->operator->postfix());
        self::assertSame('(1 IS NULL)', OperatorExpressions::write($postfix)->toString());
        $prefix = $statement->outputs[1]->expression;
        self::assertInstanceOf(UnaryExpression::class, $prefix);
        self::assertFalse($prefix->operator->postfix());
        self::assertSame('(NOT TRUE)', OperatorExpressions::write($prefix)->toString());
    }

    public function testWriteSpellsBinaryAndCollatedOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT 1 + 2, 'a' COLLATE \"C\"");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $binary = $statement->outputs[0]->expression;
        self::assertInstanceOf(BinaryExpression::class, $binary);
        self::assertSame('(1 + 2)', OperatorExpressions::write($binary)->toString());
        $collated = $statement->outputs[1]->expression;
        self::assertInstanceOf(CollatedExpression::class, $collated);
        self::assertSame('(\'a\' COLLATE "C")', OperatorExpressions::write($collated)->toString());
    }

    public function testWriteOmitsAnImplicitCast(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT 1 UNION SELECT '2'");
        self::assertInstanceOf(CompoundStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $statement->right);
        $cast = $statement->right->outputs[0]->expression;
        self::assertInstanceOf(CastExpression::class, $cast);
        self::assertSame(CastMode::Implicit, $cast->mode);
        self::assertSame("'2'", OperatorExpressions::write($cast)->toString());
        self::assertSame("SELECT 1 UNION SELECT '2'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWriteSpellsAnExplicitCastWithItsTargetType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1::text');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $cast = $statement->outputs[0]->expression;
        self::assertInstanceOf(CastExpression::class, $cast);
        self::assertSame(CastMode::Explicit, $cast->mode);
        self::assertSame('CAST(1 AS text)', OperatorExpressions::write($cast)->toString());
    }

    #[TestWith(["SELECT CONVERT('a' USING BINARY)", "SELECT CONVERT('a' USING `binary`)"])]
    #[TestWith(["SELECT CAST(1 AT TIME ZONE INTERVAL '+00:00' AS DATETIME(2))", "SELECT CAST(1 AT TIME ZONE INTERVAL '+00:00' AS DATETIME(2))"])]
    #[TestWith(["CREATE TABLE t (j JSON, INDEX ((CAST(j->'$.v' AS UNSIGNED ARRAY))))", "CREATE TABLE `t`(`j` json, INDEX((CAST((`j` -> '$.v') AS UNSIGNED ARRAY))))"])]
    public function testConversionWritesMySqlConversionForms(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }
    public function testNamedWritesInfixAndPrefixOperationsThroughTheirOperator(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 OPERATOR(geo.<->) 2, OPERATOR("Geo".@@) 3');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $infix = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedInfixOperation::class, $infix);
        self::assertSame('(1 OPERATOR("geo".<->) 2)', OperatorExpressions::named($infix)->toString());
        $prefix = $statement->outputs[1]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedPrefixOperation::class, $prefix);
        self::assertSame('(OPERATOR("Geo".@@) 3)', OperatorExpressions::named($prefix)->toString());
    }

    public function testReferenceQuotesTheQualifierAndKeepsTheSymbol(): void
    {
        self::assertSame('OPERATOR("db"."Geo".<->)', OperatorExpressions::reference(new \SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator(['db', 'Geo'], '<->'))->toString());
        self::assertSame('OPERATOR(#)', OperatorExpressions::reference(new \SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator([], '#'))->toString());
    }

    public function testQuantifiedWritesTheOperatorBeforeTheQuantifier(): void
    {
        self::assertSame('NOT LIKE ALL', OperatorExpressions::quantified(\SqlSemantics\Model\Scalar\Conditional\PatternOperator::Like, true, \SqlSemantics\Model\Scalar\Query\Quantifier::All)->toString());
        self::assertSame('OPERATOR("geo".<->) ANY', OperatorExpressions::quantified(new \SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator(['geo'], '<->'), false, \SqlSemantics\Model\Scalar\Query\Quantifier::Any)->toString());
    }

    public function testArrayKeepsAClassifiedOperatorAndItsQuantifierTogether(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 <> ALL (ARRAY[1]), 1 OPERATOR(geo.<->) ANY (ARRAY[1])');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $classified = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Conditional\ArrayComparison::class, $classified);
        self::assertSame('<> ALL', OperatorExpressions::array($classified)->toString());
        $named = $statement->outputs[1]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Conditional\ArrayComparison::class, $named);
        self::assertSame('OPERATOR("geo".<->) ANY', OperatorExpressions::array($named)->toString());
    }
}
