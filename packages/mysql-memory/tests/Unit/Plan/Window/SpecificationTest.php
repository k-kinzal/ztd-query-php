<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Plan\Window\Specification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;

#[CoversClass(Specification::class)]
#[Small]
final class SpecificationTest extends TestCase
{
    public function testFrameAnswersTheFrameClauseOfTheWindowAsWritten(): void
    {
        $frame = new Frame(FrameUnit::Rows, new FrameBound(FrameBoundKind::CurrentRow));

        self::assertSame($frame, (new Specification('w', new WindowSpec(null, [], [], $frame), [], []))->frame());
        self::assertNull((new Specification(Specification::UNNAMED, new WindowSpec(), [], []))->frame());
    }
}
