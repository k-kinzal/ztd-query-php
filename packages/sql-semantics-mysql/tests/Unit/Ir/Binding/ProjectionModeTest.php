<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;

#[CoversClass(ProjectionMode::class)]
#[Small]
final class ProjectionModeTest extends TestCase
{
    public function testCasesIdentifyTheCheckedSqlModes(): void
    {
        self::assertSame(['empty', 'ANSI_QUOTES'], array_column(ProjectionMode::cases(), 'value'));
    }
}
