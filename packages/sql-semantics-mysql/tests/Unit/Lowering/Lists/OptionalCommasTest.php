<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Lists;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lists\OptionalCommas;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(OptionalCommas::class)]
#[Medium]
final class OptionalCommasTest extends TestCase
{
    public function testBetweenIgnoresTheCommasInsideOperands(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT) UNION = (a, b), ENGINE = MERGE MAX_ROWS 5')->find('create_table_options')[0];

        self::assertSame([true, false], (new OptionalCommas($lowering))->between($node, ['create_table_option']));
    }
}
