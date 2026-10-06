<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Call\FrameRule;
use SqlSemantics\Platform\MySql\Lowering\Call\WindowRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge;
use SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment;
use SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;

#[CoversClass(WindowRule::class)]
#[Medium]
final class WindowRuleTest extends TestCase
{
    public function testFunctionLowersTheValueFunctions(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT NTH_VALUE(a, 2) FROM LAST IGNORE NULLS OVER w')->find('window_func_call')[0];
        $rule = new WindowRule($lowering, new FrameRule($lowering));
        $call = $rule->function($node);

        self::assertSame(WindowFunctionKind::NthValue, $call->kind);
        self::assertSame(CountingEdge::Last, $call->edge);
        self::assertSame(NullTreatment::Ignore, $call->nulls);
    }

    public function testFunctionReportsAnotherNode(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT 1')->find('select_item_list')[0];
        $this->expectExceptionMessage('No semantic rule is implemented for: ');

        $rule = new WindowRule($lowering, new FrameRule($lowering));
        $rule->function($node);
    }

    public function testOffsetLowersTheOffsetAndTheDefault(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT LEAD(a, ?, 0) OVER w')->find('opt_lead_lag_info')[0];
        $rule = new WindowRule($lowering, new FrameRule($lowering));
        $arguments = $rule->offset($node);

        self::assertInstanceOf(Parameter::class, $arguments[0]);
        self::assertCount(2, $arguments);
    }

    public function testStableLowersEachForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $list = $platform->parser($profile)->parse('SELECT NTILE(n) OVER w, NTILE(@v) OVER w, NTILE(4) OVER w')->find('select_item_list')[0];
        $rule = new WindowRule($lowering, new FrameRule($lowering));
        $integers = $list->find('stable_integer');

        self::assertInstanceOf(RoutineVariable::class, $rule->stable($integers[0]));
        self::assertInstanceOf(UserVariable::class, $rule->stable($integers[1]));
        self::assertInstanceOf(NumberLiteral::class, $rule->stable($integers[2]));
    }

    public function testNullsLowersNoTreatment(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT LAG(a) OVER w')->find('opt_null_treatment')[0];
        $rule = new WindowRule($lowering, new FrameRule($lowering));
        self::assertNull($rule->nulls($node));
    }

    public function testEdgeLowersFromFirst(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT NTH_VALUE(a, 1) FROM FIRST OVER w')->find('opt_from_first_last')[0];
        $rule = new WindowRule($lowering, new FrameRule($lowering));
        self::assertSame(CountingEdge::First, $rule->edge($node));
    }

    public function testWindowingLowersNoWindow(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT SUM(a)')->find('opt_windowing_clause')[0];
        $rule = new WindowRule($lowering, new FrameRule($lowering));
        self::assertNull($rule->windowing($node));
    }

    public function testWindowLowersASpecification(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT RANK() OVER (w ROWS CURRENT ROW)')->find('windowing_clause')[0];
        $rule = new WindowRule($lowering, new FrameRule($lowering));
        self::assertInstanceOf(WindowSpec::class, $rule->window($node));
    }

    public function testNameLowersAWindowName(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT RANK() OVER w')->find('window_name')[0];
        $rule = new WindowRule($lowering, new FrameRule($lowering));
        self::assertSame('w', $rule->name($node)->value);
    }
}
