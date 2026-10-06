<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\ElementRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;

#[CoversClass(ElementRule::class)]
#[Medium]
final class ElementRuleTest extends TestCase
{
    public function testElementsLowersTheElementList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, KEY (a))')->find('table_element_list')[0];
        $elements = (new ElementRule($lowering))->elements($node);

        self::assertInstanceOf(ColumnDefinition::class, $elements[0]);
        self::assertInstanceOf(IndexDefinition::class, $elements[1]);
    }

    public function testElementLowersAColumnWithItsCheck(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT NOT NULL CHECK (a > 0))')->find('column_def')[0];
        $element = (new ElementRule($lowering))->element($node);

        self::assertInstanceOf(ColumnDefinition::class, $element);
        self::assertInstanceOf(CheckConstraint::class, $element->specification->columnAttributes()[1]);
    }

    public function testColumnLowersAQualifiedFieldSpec(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (t.a INT)')->find('field_spec')[0];
        $column = (new ElementRule($lowering))->column($node);

        self::assertSame('t', $column->name->table?->name->value);
    }
}
