<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Spec\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spec\Policy\OutcomeKind;
use Spec\Policy\SqlitePolicy;
use Spec\Probe\ProbePhase;
use Spec\Probe\ProbeResult;

#[CoversClass(SqlitePolicy::class)]
final class SqlitePolicyTest extends TestCase
{
    public function testClassifyReturnsAcceptedForAcceptedProbeResult(): void
    {
        $kind = (new SqlitePolicy())->classify(ProbeResult::accepted(ProbePhase::Prepare));

        self::assertSame(OutcomeKind::Accepted, $kind);
    }

    public function testClassifyReturnsUnknownForMissingDiagnostics(): void
    {
        $kind = (new SqlitePolicy())->classify(
            ProbeResult::failed(ProbePhase::Prepare, null, null, null),
        );

        self::assertSame(OutcomeKind::Unknown, $kind);
    }

    public function testClassifyReturnsStateForMissingSchemaDiagnostics(): void
    {
        $kind = (new SqlitePolicy())->classify(
            ProbeResult::failed(ProbePhase::Prepare, null, null, 'no such table: _i0'),
        );

        self::assertSame(OutcomeKind::State, $kind);
    }

    public function testClassifyReturnsResourceForResourceDiagnostics(): void
    {
        $kind = (new SqlitePolicy())->classify(
            ProbeResult::failed(ProbePhase::Prepare, null, null, 'out of memory'),
        );

        self::assertSame(OutcomeKind::Resource, $kind);
    }

    public function testClassifyReturnsSyntaxForSyntaxDiagnostics(): void
    {
        $kind = (new SqlitePolicy())->classify(
            ProbeResult::failed(ProbePhase::Prepare, null, null, 'syntax error near FROM'),
        );

        self::assertSame(OutcomeKind::Syntax, $kind);
    }

    public function testClassifyReturnsContractForUnexpectedDiagnostics(): void
    {
        $kind = (new SqlitePolicy())->classify(
            ProbeResult::failed(ProbePhase::Prepare, null, null, 'unexpected planner failure'),
        );

        self::assertSame(OutcomeKind::Contract, $kind);
    }
}
