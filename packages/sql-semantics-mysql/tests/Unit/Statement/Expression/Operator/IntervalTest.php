<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Interval;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;

#[CoversClass(Interval::class)]
#[Medium]
final class IntervalTest extends TestCase
{
    public function testRenderWritesTheQuantityAndTheUnit(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Interval(new StringLiteral(['1:30']), IntervalUnit::HourMinute))->render($out);

        self::assertSame("INTERVAL '1:30' HOUR_MINUTE", (new Lexical())->join($out->pieces()));
    }
}
