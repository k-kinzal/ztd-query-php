<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Call\WeightRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightStringParameters;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;

#[CoversClass(WeightRule::class)]
#[Small]
final class WeightRuleTest extends TestCase
{
    public function testClaimsTellsTheProductionsOfTheRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new WeightRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertTrue($rule->claims('function_call_conflict: WEIGHT_STRING_SYM ( expr )'));
        self::assertFalse($rule->claims('function_call_conflict: IF ( expr , expr , expr )'));
    }

    public function testCallLowersEveryForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $list = $platform->parser($profile)->parse('SELECT WEIGHT_STRING(a AS BINARY(4)), WEIGHT_STRING(a, 1, 2, 3)')->find('select_item_list')[0];
        $rule = new WeightRule($lowering);
        $cast = $rule->call($lowering->form($list->find('function_call_conflict')[0]));
        $parameters = $rule->call($lowering->form($list->find('function_call_conflict')[1]));

        self::assertInstanceOf(WeightString::class, $cast);
        self::assertSame(WeightCast::Binary, $cast->cast);
        self::assertInstanceOf(WeightStringParameters::class, $parameters);
    }

    public function testCallReportsAProductionOutsideTheRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT IF(1, 2, 3)')->find('function_call_conflict')[0];
        $this->expectExceptionMessage('No semantic rule is implemented for: ');

        (new WeightRule($lowering))->call($lowering->form($node));
    }

    public function testLengthLowersTheLengthOfTheCast(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT WEIGHT_STRING(a AS CHAR(7))')->find('ws_num_codepoints')[0];
        self::assertSame('7', (new WeightRule($lowering))->length($node)->text);
    }

    public function testLevelsLowersARange(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT WEIGHT_STRING(a LEVEL 1 - 3)')->find('opt_ws_levels')[0];
        self::assertSame('3', (new WeightRule($lowering))->levels($node)[1]?->to->text);
    }

    public function testLevelLowersTheFlags(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT WEIGHT_STRING(a LEVEL 2 DESC REVERSE)')->find('ws_level_list_item')[0];
        $level = (new WeightRule($lowering))->level($node);

        self::assertSame(Direction::Descending, $level->direction);
        self::assertTrue($level->reverse);
    }

    public function testDirectionLowersAscending(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT WEIGHT_STRING(a LEVEL 2 ASC)')->find('ws_level_flag_desc')[0];
        self::assertSame(Direction::Ascending, (new WeightRule($lowering))->direction($node));
    }

    public function testNumberLowersALevelNumber(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT WEIGHT_STRING(a LEVEL 5)')->find('ws_level_number')[0];
        self::assertSame('5', (new WeightRule($lowering))->number($node)->text);
    }
}
