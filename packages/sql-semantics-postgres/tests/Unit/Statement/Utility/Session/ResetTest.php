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
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('RESET ALL')->facts->diagnostics);
    }

    public function testRenderWritesEachForm(): void
    {
        self::assertSame(['RESET ALL', 'RESET a.b', 'RESET TIME ZONE', 'RESET TRANSACTION ISOLATION LEVEL', 'RESET SESSION AUTHORIZATION'], [(new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('RESET ALL')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('RESET a.b')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('RESET TIME ZONE')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('RESET TRANSACTION ISOLATION LEVEL')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('RESET SESSION AUTHORIZATION')->toString()]);
    }
}
