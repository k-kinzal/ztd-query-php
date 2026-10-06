<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;

#[CoversClass(Report::class)]
#[Small]
final class ReportTest extends TestCase
{
    public function testCasesKeyEveryLayout(): void
    {
        self::assertSame('tables_full', Report::FullTables->value);
        self::assertCount(46, Report::cases());
    }
}
