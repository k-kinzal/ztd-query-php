<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Expression\ComparisonSlice;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;

#[CoversClass(ComparisonSlice::class)]
#[Medium]
final class ComparisonSliceTest extends TestCase
{
    public function testExpressionKeepsTheLeftAssociationOfChainedComparisons(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WHERE a = b = c');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        self::assertInstanceOf(Comparison::class, $operation->statement->where->left);
        self::assertInstanceOf(ColumnUse::class, $operation->statement->where->right);
        self::assertSame('c', $operation->statement->where->right->name->value);
        self::assertSame('SELECT a FROM t WHERE a = b = c', $operation->toString());
    }

    public function testOperatorLowersEveryComparisonSpelling(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');

        self::assertSame('SELECT a FROM t WHERE a <> 1', $semantics->analyze('SELECT a FROM t WHERE a != 1')->toString());
        self::assertSame('SELECT a FROM t WHERE a >= 1', $semantics->analyze('SELECT a FROM t WHERE a >= 1')->toString());
        self::assertSame('SELECT a FROM t WHERE a <= 1', $semantics->analyze('SELECT a FROM t WHERE a <= 1')->toString());
        self::assertSame('SELECT a FROM t WHERE a > 1', $semantics->analyze('SELECT a FROM t WHERE a > 1')->toString());
        self::assertSame('SELECT a FROM t WHERE a < 1', $semantics->analyze('SELECT a FROM t WHERE a < 1')->toString());
        self::assertSame('SELECT a FROM t WHERE a <=> NULL', $semantics->analyze('SELECT a FROM t WHERE a <=> NULL')->toString());
    }

    public function testOperatorReportsAQuantifiedComparisonAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: bool_pri: bool_pri comp_op all_or_any ( subselect )');

        (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT a FROM t WHERE a = ANY (SELECT b FROM u)');
    }

    public function testPredicateLowersTheNullSafeComparisonOfEveryRelease(): void
    {
        $modern = (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('SELECT a FROM t WHERE a <=> NULL');
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT a FROM t WHERE a <=> NULL');

        self::assertInstanceOf(Select::class, $modern->statement);
        self::assertInstanceOf(Select::class, $legacy->statement);
        self::assertInstanceOf(Comparison::class, $modern->statement->where);
        self::assertInstanceOf(Comparison::class, $legacy->statement->where);
        self::assertSame(ComparisonOperator::NullSafeEqual, $modern->statement->where->operator);
        self::assertSame(ComparisonOperator::NullSafeEqual, $legacy->statement->where->operator);
        self::assertInstanceOf(NullLiteral::class, $legacy->statement->where->right);
    }

    public function testPredicateReportsAPatternPredicateAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: predicate: bit_expr LIKE simple_expr opt_escape');

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("SELECT a FROM t WHERE a LIKE 'x%'");
    }

    public function testBitExpressionReportsABitOperatorAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: bit_expr: bit_expr | bit_expr');

        (new Semantics(Dialect::MySql))->analyze('SELECT a | 1 FROM t');
    }

    public function testSimpleExpressionLowersParametersAndVariables(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT ? = @v FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->items[0]->expression);
        self::assertInstanceOf(Parameter::class, $operation->statement->items[0]->expression->left);
        self::assertInstanceOf(UserVariable::class, $operation->statement->items[0]->expression->right);
    }

    public function testSimpleExpressionReportsAGroupingAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: simple_expr: ( expr )');

        (new Semantics(Dialect::MySql))->analyze('SELECT (a) FROM t');
    }
}
