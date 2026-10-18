<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameCondition;

#[CoversClass(FrameCondition::class)]
#[Small]
final class FrameConditionTest extends TestCase
{
    public function testCasesKeepOffsetAndOrderingConstraintsDistinct(): void
    {
        self::assertSame(['RowsInterval', 'RangeOrder', 'Offset'], array_column(FrameCondition::cases(), 'name'));
    }
}
