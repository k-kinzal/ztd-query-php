<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\TableOptionRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\InsertMethod;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat;
use SqlSemantics\Platform\MySql\Statement\Table\Option\NumberOption;

#[CoversClass(TableOptionRule::class)]
#[Medium]
final class TableOptionRuleTest extends TestCase
{
    public function testOptionsLowersTheOptionsInOrder(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT) ENGINE = x, MAX_ROWS 3')->find('create_table_options')[0];
        $options = (new TableOptionRule($lowering))->options($node);

        self::assertInstanceOf(EngineOption::class, $options[0]);
        self::assertInstanceOf(NumberOption::class, $options[1]);
    }

    public function testOptionLowersOneOption(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT) PACK_KEYS = DEFAULT')->find('create_table_option')[0];
        $option = (new TableOptionRule($lowering))->option($node);

        self::assertInstanceOf(NumberOption::class, $option);
        self::assertNull($option->value);
    }

    public function testNamedLowersANamedOption(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT) ENGINE InnoDB')->find('create_table_option')[0];
        $option = (new TableOptionRule($lowering))->named($lowering->form($node));

        self::assertInstanceOf(EngineOption::class, $option);
    }

    public function testKeywordLowersARowFormat(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT) ROW_FORMAT REDUNDANT')->find('row_types')[0];

        self::assertSame(RowFormat::Redundant, (new TableOptionRule($lowering))->keyword($node, RowFormat::class));

        $this->expectExceptionMessage('No semantic rule is implemented for: row_types: REDUNDANT_SYM');

        (new TableOptionRule($lowering))->keyword($node, InsertMethod::class);
    }
}
