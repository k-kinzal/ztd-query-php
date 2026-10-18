<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Deriver\Configuration\ReportFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportFormat::class)]
final class ReportFormatTest extends TestCase
{
    public function testCasesDefineTheSupportedCommandFormats(): void
    {
        self::assertSame([ReportFormat::Text, ReportFormat::Json], ReportFormat::cases());
    }
}
