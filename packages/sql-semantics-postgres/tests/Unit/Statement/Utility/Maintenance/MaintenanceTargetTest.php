<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\MaintenanceTarget;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(MaintenanceTarget::class)]
#[Medium]
final class MaintenanceTargetTest extends TestCase
{
    public function testRenderWritesTheTableAndItsColumns(): void
    {
        self::assertSame('ANALYZE s.t (a, "B"), u', (new Inspection())->inspect('ANALYZE s.t(A, "B"), u')->toString());
    }
}
