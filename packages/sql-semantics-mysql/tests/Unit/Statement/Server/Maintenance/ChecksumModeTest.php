<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumMode;

#[CoversClass(ChecksumMode::class)]
#[Small]
final class ChecksumModeTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['QUICK', 'EXTENDED'], array_column(ChecksumMode::cases(), 'value'));
    }
}
