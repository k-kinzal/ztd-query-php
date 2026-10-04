<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Expression\PrimaryRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

#[CoversClass(PrimaryRule::class)]
#[Medium]
final class PrimaryRuleTest extends TestCase
{
    public function testSimpleExpressionAppliesCollateAndConcatenationFromTheLeft(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', Mode::fromString('HIGH_NOT_PRECEDENCE,PIPES_AS_CONCAT'), ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $expression = (new PrimaryRule($lowering))->simpleExpression($platform->parser($profile)->parse("SELECT 'a' || 'b' COLLATE utf8mb4_bin || 'c'")->find('simple_expr')[0]);

        self::assertInstanceOf(Concatenation::class, $expression);
        self::assertInstanceOf(Concatenation::class, $expression->left);
        self::assertInstanceOf(Collated::class, $expression->left->right);
    }

    public function testUnitLowersTheTightNegationOfHighNotPrecedence(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', Mode::fromString('HIGH_NOT_PRECEDENCE,PIPES_AS_CONCAT'), ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $expression = (new PrimaryRule($lowering))->unit($platform->productions($profile)->form($platform->parser($profile)->parse('SELECT NOT 1')->find('simple_expr')[0]));

        self::assertInstanceOf(Unary::class, $expression);
        self::assertSame(UnaryOperator::Not, $expression->operator);
    }

    public function testLeafLowersAColumnAndAParameter(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new PrimaryRule($lowering);
        $tree = $platform->parser($profile)->parse('SELECT a = ?');

        self::assertInstanceOf(ColumnUse::class, $rule->leaf('column', $tree->find('simple_ident')[0]));
        self::assertInstanceOf(Parameter::class, $rule->leaf('parameter', $tree->find('param_marker')[0]));
    }

    public function testNegationRejectsAnotherNode(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);

        $this->expectExceptionMessage('No semantic rule is implemented for: comp_op: EQ');

        (new PrimaryRule($lowering))->negation($platform->parser($profile)->parse('SELECT 1 = 2')->find('comp_op')[0]);
    }
}
