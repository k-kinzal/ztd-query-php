<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\Discard::class)]
#[Medium]
final class DiscardTest extends TestCase
{
    public function testDeriveStatementRecordsNoProblem(): void
    {
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('DISCARD ALL')->facts->diagnostics);
    }

    public function testRenderKeepsTheSpelling(): void
    {
        self::assertSame(['DISCARD TEMP', 'DISCARD TEMPORARY', 'DISCARD PLANS', 'DISCARD SEQUENCES', 'DISCARD ALL'], [(new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('DISCARD TEMP')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('DISCARD TEMPORARY')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('DISCARD PLANS')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('DISCARD SEQUENCES')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('DISCARD ALL')->toString()]);
    }
}
