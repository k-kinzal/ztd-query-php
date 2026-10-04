<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Call\CallRules;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;

#[CoversClass(CallRules::class)]
#[Small]
final class CallRulesTest extends TestCase
{
    public function testCallDispatchesEveryFamilyOfCalls(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $list = $platform->parser($profile)->parse('SELECT GEOMETRYCOLLECTION(), GROUPING(a), COUNT(*), ROW_NUMBER() OVER w, NOW(), WEIGHT_STRING(a)')->find('select_item_list')[0];
        $rules = new CallRules($lowering);

        self::assertInstanceOf(KeywordCall::class, $rules->call($list->find('function_call_conflict')[0]));
        self::assertInstanceOf(KeywordCall::class, $rules->call($list->find('set_function_specification')[0]));
        self::assertInstanceOf(Aggregate::class, $rules->call($list->find('set_function_specification')[1]));
        self::assertInstanceOf(WindowFunction::class, $rules->call($list->find('window_func_call')[0]));
        self::assertInstanceOf(ClockCall::class, $rules->call($list->find('function_call_nonkeyword')[0]));
        self::assertInstanceOf(WeightString::class, $rules->call($list->find('function_call_conflict')[1]));
    }

    public function testFunctionLowersJsonValue(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse("SELECT JSON_VALUE(a, '$')")->find('function_call_keyword')[0];
        self::assertInstanceOf(JsonValueCall::class, (new CallRules($lowering))->function($lowering->form($node)));
    }

    public function testFunctionReportsAProductionOutsideTheFamily(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT 1')->find('select_item_list')[0];
        $this->expectExceptionMessage('No semantic rule is implemented for: ');

        (new CallRules($lowering))->function($lowering->form($node));
    }

    public function testCurrentTimestampLowersNow(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c DATETIME ON UPDATE NOW())')->find('now')[0];
        self::assertEquals(new ClockCall(Clock::Now), (new CallRules($lowering))->currentTimestamp($node));
    }

    public function testWindowNameLowersAWindowName(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT RANK() OVER w')->find('window_name')[0];
        self::assertSame('w', (new CallRules($lowering))->windowName($node)->value);
    }

    public function testWindowSpecificationLowersASpecification(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT RANK() OVER (ROWS UNBOUNDED PRECEDING)')->find('window_spec')[0];
        $specification = (new CallRules($lowering))->windowSpecification($node);

        self::assertInstanceOf(WindowSpec::class, $specification);
        self::assertSame(FrameBoundKind::UnboundedPreceding, $specification->frame?->start->kind);
    }

    public function testJsonTableColumnsLowersTheColumns(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse("SELECT * FROM JSON_TABLE('[]', '$' COLUMNS (n FOR ORDINALITY))")->find('columns_clause')[0];
        self::assertCount(1, (new CallRules($lowering))->jsonTableColumns($node));
    }
}
