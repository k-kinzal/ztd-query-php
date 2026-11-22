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
        self::assertSame([], (new \Tests\Support\Inspection())->inspect('DISCARD ALL')->facts->diagnostics);
    }

    public function testRenderKeepsTheSpelling(): void
    {
        self::assertSame(['DISCARD TEMP', 'DISCARD TEMPORARY', 'DISCARD PLANS', 'DISCARD SEQUENCES', 'DISCARD ALL'], [(new \Tests\Support\Inspection())->inspect('DISCARD TEMP')->toString(), (new \Tests\Support\Inspection())->inspect('DISCARD TEMPORARY')->toString(), (new \Tests\Support\Inspection())->inspect('DISCARD PLANS')->toString(), (new \Tests\Support\Inspection())->inspect('DISCARD SEQUENCES')->toString(), (new \Tests\Support\Inspection())->inspect('DISCARD ALL')->toString()]);
    }
}
