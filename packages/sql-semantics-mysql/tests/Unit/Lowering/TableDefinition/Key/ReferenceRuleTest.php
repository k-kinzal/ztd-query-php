<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\ReferenceRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceMatch;

#[CoversClass(ReferenceRule::class)]
#[Medium]
final class ReferenceRuleTest extends TestCase
{
    public function testReferencesLowersTheClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT REFERENCES p (x) MATCH FULL)')->find('references')[0];

        self::assertSame(ReferenceMatch::Full, (new ReferenceRule($lowering))->references($node)->match);
    }

    public function testOptionalLowersAnAbsentClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT)')->find('opt_references')[0];

        self::assertNull((new ReferenceRule($lowering))->optional($node));
    }

    public function testColumnsLowersTheParentColumns(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT REFERENCES p (x, y))')->find('opt_ref_list')[0];

        self::assertCount(2, (new ReferenceRule($lowering))->columns($node) ?? []);
    }

    public function testActionsKeepsTheWrittenOrder(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT REFERENCES p (x) ON DELETE CASCADE ON UPDATE SET NULL)')->find('opt_on_update_delete')[0];
        $actions = (new ReferenceRule($lowering))->actions($node);

        self::assertSame([ReferenceEvent::Delete, ReferenceEvent::Update], [$actions[0]->event, $actions[1]->event]);
    }
}
