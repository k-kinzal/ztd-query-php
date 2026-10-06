<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Call\AggregateRule;
use SqlSemantics\Platform\MySql\Lowering\Call\FrameRule;
use SqlSemantics\Platform\MySql\Lowering\Call\WindowRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(AggregateRule::class)]
#[Small]
final class AggregateRuleTest extends TestCase
{
    public function testAggregateLowersEveryArgumentForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $list = $platform->parser($profile)->parse("SELECT COUNT(ALL *), COUNT(DISTINCT a, b), SUM(ALL a) OVER w, JSON_OBJECTAGG(ALL a, b), GROUP_CONCAT(DISTINCT a SEPARATOR '-')")->find('select_item_list')[0];
        $rule = new AggregateRule($lowering, new WindowRule($lowering, new FrameRule($lowering)));
        $calls = array_map(static fn (Node $node): \SqlSemantics\Statement\Scalar => $rule->aggregate($node), $list->find('sum_expr'));

        self::assertEquals(new Aggregate(AggregateFunction::Count, [], false, true), $calls[0]);
        self::assertInstanceOf(Aggregate::class, $calls[1]);
        self::assertCount(2, $calls[1]->arguments);
        self::assertInstanceOf(Aggregate::class, $calls[2]);
        self::assertTrue($calls[2]->all);
        self::assertEquals(new Name('w'), $calls[2]->over);
        self::assertInstanceOf(JsonObjectAggregate::class, $calls[3]);
        self::assertTrue($calls[3]->keyAll);
        self::assertInstanceOf(GroupConcat::class, $calls[4]);
        self::assertSame('-', $calls[4]->separator?->value);
    }

    public function testAggregateReportsAnotherNode(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT 1')->find('select_item_list')[0];
        $this->expectExceptionMessage('No semantic rule is implemented for: ');

        (new AggregateRule($lowering, new WindowRule($lowering, new FrameRule($lowering))))->aggregate($node);
    }

    public function testConcatenationLowersGroupConcat(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT GROUP_CONCAT(a, b)')->find('sum_expr')[0];
        self::assertCount(2, (new AggregateRule($lowering, new WindowRule($lowering, new FrameRule($lowering))))->concatenation($lowering->form($node), 7)->arguments);
    }

    public function testSingleLowersOneOperandAsAList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT SUM(1)')->find('in_sum_expr')[0];
        self::assertFalse((new AggregateRule($lowering, new WindowRule($lowering, new FrameRule($lowering))))->single($node)[0]);
    }

    public function testOperandLowersAll(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT SUM(ALL 1)')->find('in_sum_expr')[0];
        self::assertTrue((new AggregateRule($lowering, new WindowRule($lowering, new FrameRule($lowering))))->operand($node)[0]);
    }

    public function testDistinctTellsWhetherDistinctIsWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT GROUP_CONCAT(DISTINCT a)')->find('opt_distinct')[0];
        self::assertTrue((new AggregateRule($lowering, new WindowRule($lowering, new FrameRule($lowering))))->distinct($node));
    }

    public function testOrderingLowersNoOrdering(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT GROUP_CONCAT(a)')->find('opt_gorder_clause')[0];
        self::assertSame([], (new AggregateRule($lowering, new WindowRule($lowering, new FrameRule($lowering))))->ordering($node));
    }

    public function testSeparatorLowersNoSeparator(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT GROUP_CONCAT(a)')->find('opt_gconcat_separator')[0];
        self::assertNull((new AggregateRule($lowering, new WindowRule($lowering, new FrameRule($lowering))))->separator($node));
    }
}
