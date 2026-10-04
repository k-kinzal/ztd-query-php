<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\KeyPartRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;

#[CoversClass(KeyPartRule::class)]
#[Medium]
final class KeyPartRuleTest extends TestCase
{
    public function testPartsPairsLegacyDirections(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, KEY (a DESC, b))')->find('key_list')[0];
        $parts = (new KeyPartRule($lowering))->parts($node);

        self::assertInstanceOf(ColumnPart::class, $parts[0]);
        self::assertSame(Direction::Descending, $parts[0]->direction);
        self::assertCount(2, $parts);
    }

    public function testColumnsLowersForeignKeyColumns(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, FOREIGN KEY (a, b) REFERENCES p (x, y))')->find('key_list')[0];

        self::assertCount(2, (new KeyPartRule($lowering))->columns($node));
    }

    public function testPartLowersAnExpressionPart(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, KEY ((a + 1) DESC))')->find('key_part_with_expression')[0];

        self::assertInstanceOf(ExpressionPart::class, (new KeyPartRule($lowering))->part($node));
    }

    public function testLengthLowersThePrefixLength(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a TEXT, KEY (a(7)))')->find('key_part')[0];

        self::assertSame('7', (new KeyPartRule($lowering))->length($node)->text);
    }
}
