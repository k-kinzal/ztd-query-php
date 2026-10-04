<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Expression\ExpressionRules;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(ExpressionRules::class)]
#[Medium]
final class ExpressionRulesTest extends TestCase
{
    public function testExpressionLowersAComparison(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WHERE a <> 1');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        self::assertSame(ComparisonOperator::NotEqual, $operation->statement->where->operator);
        self::assertSame('SELECT a FROM t WHERE a <> 1', $operation->toString());
    }

    public function testExpressionReportsAnOperatorWithoutARule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: expr: expr and expr');

        (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WHERE a = 1 AND b = 2');
    }

    public function testBitExpressionLowersTheOperandOfAComparison(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT a FROM t WHERE 1 < a');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        self::assertInstanceOf(NumberLiteral::class, $operation->statement->where->left);
        self::assertInstanceOf(ColumnUse::class, $operation->statement->where->right);
    }

    public function testBitExpressionReportsAnArithmeticOperatorWithoutARule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: bit_expr: bit_expr + bit_expr');

        (new Semantics(Dialect::MySql))->analyze('SELECT a + 1 FROM t');
    }

    public function testSimpleExpressionLowersTheLeaves(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("SELECT a, 1, 'x', ?, @v, @@sql_mode FROM t");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertCount(6, $operation->statement->items);
        self::assertSame("SELECT a, 1, 'x', ?, @v, @@sql_mode FROM t", $operation->toString());
    }

    public function testSimpleExpressionReportsAFunctionCallWithoutARule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: simple_expr: function_call_keyword');

        (new Semantics(Dialect::MySql))->analyze('SELECT USER()');
    }

    public function testExpressionsReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new ExpressionRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL expression family: expressions');

        $rules->expressions(new Node('rule', 0, []));
    }

    public function testIntervalUnitReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new ExpressionRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL expression family: intervalUnit');

        $rules->intervalUnit(new Node('rule', 0, []));
    }
}
