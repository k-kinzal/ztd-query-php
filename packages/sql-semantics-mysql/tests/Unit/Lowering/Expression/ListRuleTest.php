<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Expression\ListRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(ListRule::class)]
#[Medium]
final class ListRuleTest extends TestCase
{
    public function testExpressionsLowersAListAndAnAbsentList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new ListRule($lowering);

        self::assertCount(3, $rule->expressions($platform->parser($profile)->parse('SELECT 1 IN (1, 2, 3, 4)')->find('expr_list')[0]));
        self::assertSame([], $rule->expressions($platform->parser($profile)->parse('SELECT GEOMETRYCOLLECTION()')->find('opt_expr_list')[0]));
    }
}
