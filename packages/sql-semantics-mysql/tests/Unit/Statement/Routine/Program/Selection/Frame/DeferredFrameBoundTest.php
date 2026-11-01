<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Selection\Frame;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameBound;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(DeferredFrameBound::class)]
#[Small]
final class DeferredFrameBoundTest extends TestCase
{
    public function testRenderKeepsTheCompleteNonScalarInterval(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, \SqlSemantics\Contract\ParameterStyle::Native)));
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        (new DeferredFrameBound(M\Call\Window\FrameBoundKind::Following, $row, M\Expression\IntervalUnit::Day))->render($out);
        self::assertSame('INTERVAL (1, 2) DAY FOLLOWING', (new Lexical())->join($out->pieces()));
    }
}
