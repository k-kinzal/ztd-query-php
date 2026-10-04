<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\HistogramUpdate;

#[CoversClass(HistogramUpdate::class)]
#[Small]
final class HistogramUpdateTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['MANUAL UPDATE', 'AUTO UPDATE'], array_column(HistogramUpdate::cases(), 'value'));
    }
}
