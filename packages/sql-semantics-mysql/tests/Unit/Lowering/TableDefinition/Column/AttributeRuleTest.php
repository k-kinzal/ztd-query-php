<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Column\AttributeRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\Column\StorageAttribute;

#[CoversClass(AttributeRule::class)]
#[Medium]
final class AttributeRuleTest extends TestCase
{
    public function testAttributeLowersAKeywordAttribute(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT AUTO_INCREMENT)')->find('attribute')[0];
        $attribute = (new AttributeRule($lowering))->attribute($node);

        self::assertInstanceOf(KeywordAttribute::class, $attribute);
        self::assertSame(ColumnKeyword::AutoIncrement, $attribute->keyword);
    }

    public function testValuedLowersAnAttributeWithAValue(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT DEFAULT 3)')->find('column_attribute')[0];

        self::assertInstanceOf(DefaultLiteral::class, (new AttributeRule($lowering))->valued($lowering->form($node)));
    }

    public function testMarksConfirmsTheKeywordRules(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT NOT NULL PRIMARY KEY)')->find('column_attribute')[1];
        (new AttributeRule($lowering))->marks($lowering->form($node));
        (new AttributeRule($lowering))->marks($lowering->form($platform->parser($profile)->parse('CREATE TABLE t (a INT NOT NULL)')->find('column_attribute')[0]));

        self::assertInstanceOf(StorageAttribute::class, (new AttributeRule($lowering))->attribute($platform->parser($profile)->parse('CREATE TABLE t (a INT STORAGE DISK)')->find('column_attribute')[0]));
    }

    public function testValueLowersALiteralDefault(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT DEFAULT 3)')->find('now_or_signed_literal')[0];

        self::assertInstanceOf(NumberLiteral::class, (new AttributeRule($lowering))->value($node));
    }

    public function testCollationLowersACollationName(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a TEXT COLLATE utf8mb4_bin)')->find('collation_name')[0];

        self::assertSame('utf8mb4_bin', (new AttributeRule($lowering))->collation($node)->name?->value);
    }
}
