<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility\Columns;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Utility\Columns\ServerReports;

#[CoversClass(ServerReports::class)]
#[Small]
final class ServerReportsTest extends TestCase
{
    public function testRowsHoldTheLayouts(): void
    {
        self::assertArrayHasKey('grants', ServerReports::ROWS);
    }
}
