<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;

#[CoversClass(FrameUnit::class)]
#[Small]
final class FrameUnitTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['ROWS', 'RANGE', 'GROUPS'], array_column(FrameUnit::cases(), 'value'));
    }
}
