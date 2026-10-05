<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\DiagnosticsArea;

#[CoversClass(DiagnosticsArea::class)]
#[Small]
final class DiagnosticsAreaTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['CURRENT', 'STACKED'], array_map(static fn (DiagnosticsArea $area): string => $area->value, DiagnosticsArea::cases()));
    }
}
