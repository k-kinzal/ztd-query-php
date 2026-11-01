<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Selection\Frame;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameExclusion;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrame;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameBound;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(DeferredFrame::class)]
#[Small]
final class DeferredFrameTest extends TestCase
{
    public function testOffsetsAnswersTheOffsetsInOrder(): void
    {
        $start = new M\Expression\Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $end = new NumberLiteral('2');
        $frame = new DeferredFrame(FrameUnit::Rows, new DeferredFrameBound(FrameBoundKind::Preceding, $start, M\Expression\IntervalUnit::Day), new FrameBound(FrameBoundKind::Following, $end));

        self::assertSame([$start, $end], $frame->offsets());
    }

    public function testRenderWritesTheBetweenFormAndTheExclusion(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new DeferredFrame(FrameUnit::Range, new DeferredFrameBound(FrameBoundKind::Preceding, new M\Expression\Row([new NumberLiteral('1'), new NumberLiteral('2')]), M\Expression\IntervalUnit::Day), new FrameBound(FrameBoundKind::UnboundedFollowing), FrameExclusion::NoOthers))->render($out);

        self::assertSame('RANGE BETWEEN INTERVAL (1, 2) DAY PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE NO OTHERS', (new Lexical())->join($out->pieces()));
    }

}
