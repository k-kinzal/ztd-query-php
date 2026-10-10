<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Evaluation\Window\WindowFrame;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Window;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

#[CoversClass(Window::class)]
#[Small]
final class WindowTest extends TestCase
{
    public function testWidthAddsOneValuePerWindowFunction(): void
    {
        $rank = new Analytic(WindowFunctionKind::Rank, null, [], 1, Domain::integer());

        self::assertSame(4, (new Window(new ZeroRows(2), [], [], WindowFrame::default(false), [$rank, $rank]))->width());
    }
}
