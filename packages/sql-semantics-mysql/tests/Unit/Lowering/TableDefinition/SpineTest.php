<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Spine;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(Spine::class)]
#[Medium]
final class SpineTest extends TestCase
{
    public function testItemsAnswersTheItemsInSourceOrder(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, b INT, c INT)')->find('table_element_list')[0];
        $items = (new Spine($lowering))->items($node, ['table_element_list: table_element', 'table_element_list: table_element_list , table_element'], ['table_element']);

        self::assertCount(3, $items);
        self::assertSame('table_element', $items[2]->name);
    }

    public function testItemsReportsAnUnlistedProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, b INT)')->find('table_element_list')[0];

        $this->expectExceptionMessage('No semantic rule is implemented for: table_element_list: table_element_list , table_element');

        (new Spine($lowering))->items($node, ['table_element_list: table_element'], ['table_element']);
    }
}
