<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\MaintenanceTarget;

#[CoversClass(MaintenanceTarget::class)]
#[Medium]
final class MaintenanceTargetTest extends TestCase
{
    public function testRenderWritesTheTableAndItsColumns(): void
    {
        self::assertSame('ANALYZE s.t (a, "B"), u', (new Semantics(Dialect::PostgreSql))->analyze('ANALYZE s.t(A, "B"), u')->toString());
    }
}
