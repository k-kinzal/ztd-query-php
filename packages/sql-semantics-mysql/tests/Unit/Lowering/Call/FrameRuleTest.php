<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Call\FrameRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameExclusion;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;

#[CoversClass(FrameRule::class)]
#[Small]
final class FrameRuleTest extends TestCase
{
    public function testSpecificationLowersTheParts(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT RANK() OVER (w RANGE BETWEEN INTERVAL 1 DAY PRECEDING AND 2 FOLLOWING EXCLUDE TIES)')->find('window_spec')[0];
        $rule = new FrameRule($lowering);
        $specification = $rule->specification($node);

        self::assertSame('w', $specification->base?->value);
        self::assertSame(FrameUnit::Range, $specification->frame?->unit);
        self::assertSame(IntervalUnit::Day, $specification->frame->start->unit);
        self::assertSame(FrameBoundKind::Following, $specification->frame->end?->kind);
        self::assertSame(FrameExclusion::Ties, $specification->frame->exclusion);
    }

    public function testBaseLowersNoBase(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT RANK() OVER ()')->find('opt_existing_window_name')[0];
        $rule = new FrameRule($lowering);
        self::assertNull($rule->base($node));
    }

    public function testOrderingLowersNoPartition(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT RANK() OVER ()')->find('opt_partition_clause')[0];
        $rule = new FrameRule($lowering);
        self::assertSame([], $rule->ordering($node));
    }

    public function testFrameLowersNoFrame(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT RANK() OVER ()')->find('opt_window_frame_clause')[0];
        $rule = new FrameRule($lowering);
        self::assertNull($rule->frame($node));
    }

    public function testBoundLowersAParameterOffset(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT RANK() OVER (ROWS ? PRECEDING)')->find('window_frame_start')[0];
        $rule = new FrameRule($lowering);
        self::assertInstanceOf(Parameter::class, $rule->bound($node)->offset);
    }
}
