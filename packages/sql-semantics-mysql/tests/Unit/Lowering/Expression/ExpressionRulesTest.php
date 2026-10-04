<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Expression\ExpressionRules;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(ExpressionRules::class)]
#[Medium]
final class ExpressionRulesTest extends TestCase
{
    public function testExpressionLowersALogicalOperation(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);

        self::assertInstanceOf(Logical::class, $lowering->expressions->expression($platform->parser($profile)->parse('SELECT 1 AND 2')->find('expr')[0]));
    }

    public function testBitExpressionLowersAnArithmeticOperation(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);

        self::assertInstanceOf(Arithmetic::class, $lowering->expressions->bitExpression($platform->parser($profile)->parse('SELECT 1 + 2')->find('bit_expr')[0]));
    }

    public function testSimpleExpressionLowersACollation(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);

        self::assertInstanceOf(Collated::class, $lowering->expressions->simpleExpression($platform->parser($profile)->parse("SELECT 'a' COLLATE utf8mb4_bin")->find('simple_expr')[0]));
    }

    public function testExpressionsLowersAListInOrder(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $expressions = $lowering->expressions->expressions($platform->parser($profile)->parse('SELECT 1 IN (2, 3, 4)')->find('expr_list')[0]);

        self::assertCount(2, $expressions);
        self::assertInstanceOf(NumberLiteral::class, $expressions[1]);
        self::assertSame('4', $expressions[1]->text);
    }

    public function testIntervalUnitLowersAUnit(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);

        self::assertSame(IntervalUnit::Quarter, $lowering->expressions->intervalUnit($platform->parser($profile)->parse('SELECT 1 + INTERVAL 1 QUARTER')->find('interval')[0]));
    }

    public function testIntervalLowersTheQuantityAndTheUnit(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('SELECT 1 + INTERVAL 7 WEEK');
        $interval = $lowering->expressions->interval($tree->find('expr')[1], $tree->find('interval')[0]);

        self::assertInstanceOf(NumberLiteral::class, $interval->quantity);
        self::assertSame(['7', IntervalUnit::Week], [$interval->quantity->text, $interval->unit]);
    }
}
