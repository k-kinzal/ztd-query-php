<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Spec\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spec\Policy\MySqlPolicy;
use Spec\Policy\OutcomeKind;
use Spec\Probe\ProbePhase;
use Spec\Probe\ProbeResult;

#[CoversClass(MySqlPolicy::class)]
final class MySqlPolicyTest extends TestCase
{
    public function testClassifyReturnsAcceptedForAcceptedProbeResult(): void
    {
        $kind = (new MySqlPolicy())->classify(ProbeResult::accepted(ProbePhase::Prepare));

        self::assertSame(OutcomeKind::Accepted, $kind);
    }

    public function testClassifyReturnsUnknownForPrepareFailureWithoutErrorCode(): void
    {
        $kind = (new MySqlPolicy())->classify(
            ProbeResult::failed(ProbePhase::Prepare, null, null, 'PDO::prepare returned false'),
        );

        self::assertSame(OutcomeKind::Unknown, $kind);
    }

    public function testClassifyReturnsStateForKnownStateErrorCodes(): void
    {
        $kind = (new MySqlPolicy())->classify(
            ProbeResult::failed(ProbePhase::Prepare, '42000', 1054, 'Unknown column'),
        );

        self::assertSame(OutcomeKind::State, $kind);
    }

    public function testClassifyReturnsEnvironmentForKnownEnvironmentErrorCodes(): void
    {
        $kind = (new MySqlPolicy())->classify(
            ProbeResult::failed(ProbePhase::Prepare, '42000', 1235, 'This version of MySQL does not yet support'),
        );

        self::assertSame(OutcomeKind::Environment, $kind);
    }

    public function testClassifyReturnsSyntaxForSyntaxErrors(): void
    {
        $kind = (new MySqlPolicy())->classify(
            ProbeResult::failed(ProbePhase::Prepare, '42000', 1064, 'You have an error in your SQL syntax'),
        );

        self::assertSame(OutcomeKind::Syntax, $kind);
    }

    public function testClassifyReturnsContractForUnexpectedErrorCodes(): void
    {
        $kind = (new MySqlPolicy())->classify(
            ProbeResult::failed(ProbePhase::Prepare, 'HY000', 9999, 'Unexpected failure'),
        );

        self::assertSame(OutcomeKind::Contract, $kind);
    }
}
