<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest as ParsedNullTest;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(PredicateLowering::class)]
#[Small]
final class PredicateLoweringTest extends TestCase
{
    public function testLowerResolvesSignedPredicates(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE (1 < 2)');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $bound = $operation->binding();

        $predicate = (new PredicateLowering())->lower($select->where, new SourceBinding($bound, null), true);
        self::assertInstanceOf(IntegerComparison::class, $predicate);
        self::assertSame('<', $predicate->operator->value);
    }

    public function testLowerValueResolvesNestedTruth(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE (NOT NULL) IS NULL');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($select->where);
        $bound = $operation->binding();

        $predicate = (new PredicateLowering())->lowerValue($select->where, new SourceBinding($bound, null), true);
        self::assertInstanceOf(NullTest::class, $predicate);
        self::assertInstanceOf(LogicalPredicate::class, $predicate->operand);
        self::assertSame('NOT', $predicate->operand->operator->value);
    }

    public function testComparisonPreservesOperandWidths(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE 1 < 9223372036854775807');
        self::assertInstanceOf(Select::class, $operation->statement);
        $parsed = $operation->statement->where;
        self::assertInstanceOf(BinaryOperation::class, $parsed);

        $comparison = (new PredicateLowering())->comparison($parsed, new SourceBinding($operation->binding(), null), true);

        self::assertInstanceOf(IntegerComparison::class, $comparison);
        self::assertInstanceOf(IntegerLiteral::class, $comparison->left);
        self::assertInstanceOf(WideIntegerLiteral::class, $comparison->right);
    }

    public function testSignedOperandPreservesAGroupedBigintEndpoint(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE ((-9223372036854775808)) >= 0');
        self::assertInstanceOf(Select::class, $operation->statement);
        $parsed = $operation->statement->where;
        self::assertInstanceOf(BinaryOperation::class, $parsed);

        $operand = (new PredicateLowering())->signedOperand($parsed->left, new SourceBinding($operation->binding(), null), true);

        self::assertInstanceOf(WideIntegerLiteral::class, $operand);
        self::assertSame('-9223372036854775808', $operand->decimal);
    }

    public function testSignedOperatorResolvesAnExplicitCatalogOperator(): void
    {
        $statement = (new Inspection())->inspect('SELECT 1 WHERE 3 OPERATOR(pg_catalog.>=) 2')->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(BinaryOperation::class, $statement->where);

        self::assertSame('>=', (new PredicateLowering())->signedOperator($statement->where, false)->value);
    }

    public function testNullTestRetainsTheSignedConstantAndNegation(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE 3 IS NOT NULL');
        self::assertInstanceOf(Select::class, $operation->statement);
        $test = $operation->statement->where;
        self::assertInstanceOf(ParsedNullTest::class, $test);
        $binding = new SourceBinding($operation->binding(), null);

        $value = (new PredicateLowering())->nullTest($test, $binding, true);

        self::assertTrue($value->negated);
        self::assertEquals(new IntegerLiteral('3'), $value->operand);
    }

    public function testLeafReturnsTheClosedNullSafePredicate(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE 1 IS DISTINCT FROM NULL');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertNotNull($operation->statement->where);

        $test = (new PredicateLowering())->leaf($operation->statement->where, new SourceBinding($operation->binding(), null), true);
        self::assertInstanceOf(IntegerDistinctness::class, $test);
        self::assertFalse($test->negated);
    }
}
