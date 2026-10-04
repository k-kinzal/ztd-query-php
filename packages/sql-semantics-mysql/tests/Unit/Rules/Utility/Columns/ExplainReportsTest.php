<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility\Columns;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Utility\Columns\ExplainReports;

#[CoversClass(ExplainReports::class)]
#[Small]
final class ExplainReportsTest extends TestCase
{
    public function testRowsHoldTheLayouts(): void
    {
        self::assertArrayHasKey('explain_document', ExplainReports::ROWS);
    }
}
