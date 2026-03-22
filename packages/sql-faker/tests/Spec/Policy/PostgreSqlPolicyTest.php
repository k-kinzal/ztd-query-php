<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Spec\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spec\Policy\OutcomeKind;
use Spec\Policy\PostgreSqlPolicy;
use Spec\Probe\ProbePhase;
use Spec\Probe\ProbeResult;

#[CoversClass(PostgreSqlPolicy::class)]
final class PostgreSqlPolicyTest extends TestCase
{
    public function testClassifyReturnsAcceptedForAcceptedProbeResult(): void
    {
        $kind = (new PostgreSqlPolicy())->classify(ProbeResult::accepted(ProbePhase::Execute));

        self::assertSame(OutcomeKind::Accepted, $kind);
    }

    public function testClassifyReturnsUnknownWhenSqlStateIsMissing(): void
    {
        $kind = (new PostgreSqlPolicy())->classify(
            ProbeResult::failed(ProbePhase::Execute, null, null, 'unknown failure'),
        );

        self::assertSame(OutcomeKind::Unknown, $kind);
    }

    public function testClassifyReturnsStateForKnownStateSqlStates(): void
    {
        $kind = (new PostgreSqlPolicy())->classify(
            ProbeResult::failed(ProbePhase::Execute, '42P01', 7, 'relation does not exist'),
        );

        self::assertSame(OutcomeKind::State, $kind);
    }

    public function testClassifyReturnsEnvironmentForKnownEnvironmentSqlStates(): void
    {
        $kind = (new PostgreSqlPolicy())->classify(
            ProbeResult::failed(ProbePhase::Execute, '0A000', 7, 'feature not supported'),
        );

        self::assertSame(OutcomeKind::Environment, $kind);
    }

    public function testClassifyReturnsResourceForKnownResourceSqlStates(): void
    {
        $kind = (new PostgreSqlPolicy())->classify(
            ProbeResult::failed(ProbePhase::Execute, '53200', 7, 'out of memory'),
        );

        self::assertSame(OutcomeKind::Resource, $kind);
    }

    public function testClassifyReturnsSyntaxForSyntaxSqlState(): void
    {
        $kind = (new PostgreSqlPolicy())->classify(
            ProbeResult::failed(ProbePhase::Execute, '42601', 7, 'syntax error'),
        );

        self::assertSame(OutcomeKind::Syntax, $kind);
    }

    public function testClassifyHandlesSpecialRoleLookupDiagnosticAsState(): void
    {
        $kind = (new PostgreSqlPolicy())->classify(
            ProbeResult::failed(ProbePhase::Execute, '22023', 7, 'role "_i0" does not exist'),
        );

        self::assertSame(OutcomeKind::State, $kind);
    }
}
