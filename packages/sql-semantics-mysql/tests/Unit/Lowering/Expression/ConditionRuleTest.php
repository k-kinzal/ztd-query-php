<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Expression\ConditionRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\MySql\Statement\Expression\Truth;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(ConditionRule::class)]
#[Medium]
final class ConditionRuleTest extends TestCase
{
    public function testExpressionBuildsTheLeftAssociationOfAChain(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $expression = (new ConditionRule($lowering))->expression($platform->parser($profile)->parse('SELECT 1 OR 2 && 3 OR 4')->find('expr')[0]);

        self::assertInstanceOf(Logical::class, $expression);
        self::assertInstanceOf(Logical::class, $expression->left);
        self::assertSame([LogicalOperator::Or, LogicalOperator::Or], [$expression->operator, $expression->left->operator]);
        self::assertInstanceOf(Logical::class, $expression->left->right);
        self::assertSame(LogicalOperator::And, $expression->left->right->operator);
    }

    public function testUnitLowersNotAndTheTruthTests(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new ConditionRule($lowering);
        $not = $rule->unit($platform->productions($profile)->form($platform->parser($profile)->parse('SELECT NOT 1')->find('expr')[0]));
        $truth = $rule->unit($platform->productions($profile)->form($platform->parser($profile)->parse('SELECT 1 IS NOT FALSE')->find('expr')[0]));

        self::assertInstanceOf(Not::class, $not);
        self::assertInstanceOf(TruthTest::class, $truth);
        self::assertSame([Truth::False, true], [$truth->truth, $truth->negated]);
    }

    public function testBooleanLowersAChainOfNullTestsAndComparisons(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $expression = (new ConditionRule($lowering))->boolean($platform->parser($profile)->parse('SELECT 1 = 2 IS NULL <=> 3')->find('bool_pri')[0]);

        self::assertInstanceOf(Comparison::class, $expression);
        self::assertSame(ComparisonOperator::NullSafeEqual, $expression->operator);
        self::assertInstanceOf(NullTest::class, $expression->left);
    }

    public function testApplyLowersAQuantifiedComparison(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('SELECT 1 < ALL (SELECT 2)');
        $expression = (new ConditionRule($lowering))->apply($platform->productions($profile)->form($tree->find('bool_pri')[0]), new NumberLiteral('1'));

        self::assertInstanceOf(QuantifiedComparison::class, $expression);
        self::assertSame([ComparisonOperator::Less, Quantifier::All], [$expression->operator, $expression->quantifier]);
    }

    public function testNegatedNullLowersIsNotNull(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $form = $platform->productions($profile)->form($platform->parser($profile)->parse('SELECT 1 IS NOT NULL')->find('bool_pri')[0]);

        self::assertTrue((new ConditionRule($lowering))->negatedNull($form, new NumberLiteral('1'))->negated);
    }

    public function testOperatorLowersTheComparisonOperators(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);

        self::assertSame(ComparisonOperator::GreaterOrEqual, (new ConditionRule($lowering))->operator($platform->parser($profile)->parse('SELECT 1 >= 2')->find('comp_op')[0]));
    }

    public function testQuantifierLowersSomeAsAny(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);

        self::assertSame(Quantifier::Any, (new ConditionRule($lowering))->quantifier($platform->parser($profile)->parse('SELECT 1 = SOME (SELECT 2)')->find('all_or_any')[0]));
    }

    public function testSynonymRejectsANodeThatIsNoLogicalKeyword(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);

        $this->expectExceptionMessage('No semantic rule is implemented for: comp_op: EQ');

        (new ConditionRule($lowering))->synonym($platform->parser($profile)->parse('SELECT 1 = 2')->find('comp_op')[0]);
    }
}
