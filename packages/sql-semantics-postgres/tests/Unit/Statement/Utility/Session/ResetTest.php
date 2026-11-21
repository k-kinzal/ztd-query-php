<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\Reset::class)]
#[Medium]
final class ResetTest extends TestCase
{
    public function testDeriveStatementRecordsNoProblem(): void
    {
        self::assertSame([], (new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect('RESET ALL')->facts->diagnostics);
    }

    public function testRenderWritesEachForm(): void
    {
        self::assertSame(['RESET ALL', 'RESET a.b', 'RESET TIME ZONE', 'RESET TRANSACTION ISOLATION LEVEL', 'RESET SESSION AUTHORIZATION'], [(new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect('RESET ALL')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect('RESET a.b')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect('RESET TIME ZONE')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect('RESET TRANSACTION ISOLATION LEVEL')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect('RESET SESSION AUTHORIZATION')->toString()]);
    }
}
