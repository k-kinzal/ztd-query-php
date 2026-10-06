<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FrameBound::class)]
#[Small]
final class FrameBoundTest extends TestCase
{
    public function testRenderWritesTheOffsetAndTheKind(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $interval = new Output($platform->codec($profile));
        (new FrameBound(FrameBoundKind::Following, new ColumnUse(new Name('a')), IntervalUnit::Day))->render($interval);
        $parameter = new Output($platform->codec($profile));
        (new FrameBound(FrameBoundKind::Preceding, new Parameter('?')))->render($parameter);

        self::assertSame('INTERVAL a DAY FOLLOWING', (new Lexical())->join($interval->pieces()));
        self::assertSame('? PRECEDING', (new Lexical())->join($parameter->pieces()));
    }

}
