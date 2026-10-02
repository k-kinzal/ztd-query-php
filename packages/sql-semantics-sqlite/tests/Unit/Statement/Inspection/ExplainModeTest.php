<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Inspection\ExplainMode;

#[CoversClass(ExplainMode::class)]
#[Small]
final class ExplainModeTest extends TestCase
{
    public function testCasesNameTheTwoReports(): void
    {
        self::assertSame(['Program', 'QueryPlan'], array_column(ExplainMode::cases(), 'name'));
    }
}
