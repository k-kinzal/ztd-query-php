<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Expression\PredicateRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\SoundsLike;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;

#[CoversClass(PredicateRule::class)]
#[Medium]
final class PredicateRuleTest extends TestCase
{
    public function testPredicateLowersANegatedRangeWithARangeInTheUpperBound(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $predicate = (new PredicateRule($lowering))->predicate($platform->parser($profile)->parse('SELECT 1 NOT BETWEEN 2 AND 3 BETWEEN 4 AND 5')->find('predicate')[0]);

        self::assertInstanceOf(Between::class, $predicate);
        self::assertTrue($predicate->negated);
        self::assertInstanceOf(Between::class, $predicate->high);
    }

    public function testOperationLowersTheListAndSubqueryFormsOfIn(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new PredicateRule($lowering);
        $list = $rule->operation($platform->productions($profile)->form($platform->parser($profile)->parse('SELECT 1 IN (2)')->find('predicate')[0]), new NumberLiteral('1'), 0);
        $query = $rule->operation($platform->productions($profile)->form($platform->parser($profile)->parse('SELECT 1 NOT IN (SELECT 2)')->find('predicate')[0]), new NumberLiteral('1'), 1);

        self::assertInstanceOf(InList::class, $list);
        self::assertCount(1, $list->elements);
        self::assertInstanceOf(InQuery::class, $query);
        self::assertTrue($query->negated);
    }

    public function testOperationLowersSoundsLike(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $form = $platform->productions($profile)->form($platform->parser($profile)->parse("SELECT 'a' SOUNDS LIKE 'b'")->find('predicate')[0]);

        self::assertInstanceOf(SoundsLike::class, (new PredicateRule($lowering))->operation($form, new StringLiteral(['a']), 0));
    }

    public function testMemberLowersMemberWithoutOf(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $form = $platform->productions($profile)->form($platform->parser($profile)->parse("SELECT 1 MEMBER ('[1]')")->find('predicate')[0]);
        $member = (new PredicateRule($lowering))->member($form, new NumberLiteral('1'));

        self::assertInstanceOf(StringLiteral::class, $member->array);
    }

    public function testEscapeLowersTheEscapeClauseOfMySql5(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new PredicateRule($lowering);
        $like = $rule->predicate($platform->parser($profile)->parse("SELECT 'a' LIKE 'b' ESCAPE '!'")->find('predicate')[0]);

        self::assertInstanceOf(Like::class, $like);
        self::assertInstanceOf(StringLiteral::class, $rule->escape($platform->parser($profile)->parse("SELECT 'a' LIKE 'b' ESCAPE '!'")->find('opt_escape')[0]));
        self::assertNull($rule->escape($platform->parser($profile)->parse("SELECT 'a' LIKE 'b'")->find('opt_escape')[0]));
    }
}
