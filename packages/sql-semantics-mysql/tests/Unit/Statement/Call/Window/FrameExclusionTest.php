<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameExclusion;

#[CoversClass(FrameExclusion::class)]
#[Small]
final class FrameExclusionTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['CURRENT ROW', 'GROUP', 'TIES', 'NO OTHERS'], array_column(FrameExclusion::cases(), 'value'));
    }
}
