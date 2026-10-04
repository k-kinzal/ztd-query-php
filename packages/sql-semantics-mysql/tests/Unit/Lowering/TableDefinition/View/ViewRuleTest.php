<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\View\ViewRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm;
use SqlSemantics\Platform\MySql\Statement\View\ViewCheckOption;

#[CoversClass(ViewRule::class)]
#[Medium]
final class ViewRuleTest extends TestCase
{
    public function testCreateLowersAViewTail(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE OR REPLACE VIEW v AS SELECT 1 WITH LOCAL CHECK OPTION')->find('view_tail')[0];
        $prefix = $platform->parser($profile)->parse('CREATE OR REPLACE VIEW v AS SELECT 1')->find('view_replace_or_algorithm')[0];
        $view = (new ViewRule($lowering))->create($node, $prefix, null);

        self::assertTrue($view->orReplace);
        self::assertSame(ViewCheckOption::Local, $view->definition->check);
    }

    public function testPrefixLowersReplaceAndAlgorithm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE OR REPLACE ALGORITHM = MERGE VIEW v AS SELECT 1')->find('view_replace_or_algorithm')[0];

        self::assertSame([true, ViewAlgorithm::Merge], (new ViewRule($lowering))->prefix($node));
    }

    public function testReplaceConfirmsOrReplace(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE OR REPLACE VIEW v AS SELECT 1')->find('view_replace')[0];

        self::assertTrue((new ViewRule($lowering))->replace($node));
    }

    public function testAlgorithmLowersTheAlgorithm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE ALGORITHM = UNDEFINED VIEW v AS SELECT 1')->find('view_algorithm')[0];

        self::assertSame(ViewAlgorithm::Undefined, (new ViewRule($lowering))->algorithm($node));
    }

    public function testDefinitionLowersTheTail(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE VIEW v (a) AS SELECT 1')->find('view_tail')[0];

        self::assertSame('a', (new ViewRule($lowering))->definition($node, null, null)->columns[0]->value ?? null);
    }

    public function testColumnsLowersALegacyColumnList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE VIEW v (a, b) AS SELECT 1, 2')->find('view_list_opt')[0];

        self::assertCount(2, (new ViewRule($lowering))->columns($node) ?? []);
    }

    public function testDerivedLowersAnAbsentColumnList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE VIEW v AS SELECT 1')->find('opt_derived_column_list')[0];

        self::assertNull((new ViewRule($lowering))->derived($node));
    }

    public function testAlterLowersAlterView(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('ALTER VIEW v AS SELECT 1')->find('alter_view_stmt')[0];

        self::assertSame('v', (new ViewRule($lowering))->alter($lowering->form($node))->definition->name->name->value);
    }

    public function testDropLowersDropView(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('DROP VIEW IF EXISTS v')->find('drop')[0];

        self::assertTrue((new ViewRule($lowering))->drop($lowering->form($node))->ifExists);
    }
}
