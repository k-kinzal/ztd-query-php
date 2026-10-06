<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Window\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameExclusion;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Frame::class)]
#[Small]
final class FrameTest extends TestCase
{
    public function testOffsetsAnswersTheOffsetsInOrder(): void
    {
        $start = new NumberLiteral('1');
        $end = new NumberLiteral('2');
        $frame = new Frame(FrameUnit::Rows, new FrameBound(FrameBoundKind::Preceding, $start), new FrameBound(FrameBoundKind::Following, $end));

        self::assertSame([$start, $end], $frame->offsets());
    }

    public function testRenderWritesTheBetweenFormAndTheExclusion(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new Frame(FrameUnit::Range, new FrameBound(FrameBoundKind::CurrentRow), new FrameBound(FrameBoundKind::UnboundedFollowing), FrameExclusion::NoOthers))->render($out);

        self::assertSame('RANGE BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING EXCLUDE NO OTHERS', (new Lexical())->join($out->pieces()));
    }

}
