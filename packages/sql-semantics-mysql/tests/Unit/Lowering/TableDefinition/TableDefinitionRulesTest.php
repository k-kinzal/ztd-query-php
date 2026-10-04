<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\TableDefinitionRules;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Platform\MySql\Statement\View\DropView;

#[CoversClass(TableDefinitionRules::class)]
#[Medium]
final class TableDefinitionRulesTest extends TestCase
{
    public function testStatementLowersCreateTable(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT)')->find('create_table_stmt')[0];

        self::assertInstanceOf(CreateTable::class, (new TableDefinitionRules($lowering))->statement($node));
    }

    public function testDefinitionLowersALegacyCreateIndex(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE INDEX i ON t (a)')->find('create')[0];

        self::assertInstanceOf(CreateIndex::class, (new TableDefinitionRules($lowering))->definition($lowering->form($node)));
        self::assertInstanceOf(DropView::class, (new TableDefinitionRules($lowering))->definition($lowering->form($platform->parser($profile)->parse('DROP VIEW v')->find('drop')[0])));
    }

    public function testCreateViewLowersAView(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE VIEW v AS SELECT 1')->find('view_tail')[0];

        self::assertInstanceOf(CreateView::class, (new TableDefinitionRules($lowering))->createView($node, null, null));
    }

    public function testTableElementLowersAColumn(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT)')->find('column_def')[0];

        self::assertInstanceOf(ColumnDefinition::class, (new TableDefinitionRules($lowering))->tableElement($node));
    }

    public function testTableElementsLowersTheList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, b INT)')->find('create_field_list')[0];

        self::assertCount(2, (new TableDefinitionRules($lowering))->tableElements($node));
    }

    public function testColumnSpecificationLowersAFieldDefWithItsReference(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT REFERENCES p (x))')->find('field_def')[0];
        $references = $platform->parser($profile)->parse('CREATE TABLE t (a INT REFERENCES p (x))')->find('opt_references')[0];
        $specification = (new TableDefinitionRules($lowering))->columnSpecification($node, $references);

        self::assertInstanceOf(OrdinaryColumn::class, $specification);
        self::assertNotNull($specification->references);
    }

    public function testTableOptionsLowersTheOptions(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('ALTER TABLE t ENGINE = x')->find('create_table_options_space_separated')[0];
        $options = (new TableDefinitionRules($lowering))->tableOptions($node);

        self::assertInstanceOf(EngineOption::class, $options[0]);
    }

    public function testVisibleTellsWhetherVisibleIsWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT VISIBLE)')->find('visibility')[0];

        self::assertTrue((new TableDefinitionRules($lowering))->visible($node));
    }

    public function testEnforcedTellsWhetherEnforcedIsWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT CHECK (a > 0) ENFORCED)')->find('constraint_enforcement')[0];

        self::assertTrue((new TableDefinitionRules($lowering))->enforced($node));
    }
}
