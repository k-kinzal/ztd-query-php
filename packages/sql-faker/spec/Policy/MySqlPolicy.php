<?php

declare(strict_types=1);

namespace Spec\Policy;

use Spec\Probe\ProbeResult;

/**
 * Interprets MySQL diagnostics for spec witnesses and separates expected
 * schema/environment rejections from syntax or contract failures.
 */
final class MySqlPolicy implements OutcomePolicy
{
    /**
     * Returns the SQL dialect handled by this policy.
     */
    public function dialect(): string
    {
        return 'mysql';
    }

    /**
     * Maps a normalized MySQL probe result to the outcome category used by claims.
     */
    public function classify(ProbeResult $probeResult): OutcomeKind
    {
        if ($probeResult->accepted) {
            return OutcomeKind::Accepted;
        }

        if ($probeResult->message === 'PDO::prepare returned false' || $probeResult->errorCode === null) {
            return OutcomeKind::Unknown;
        }

        return match ($probeResult->errorCode) {
            1054, 1046, 1049, 1051, 1205, 1305, 1319, 1096, 1327, 3572 => OutcomeKind::State,
            1235, 1286, 3652 => OutcomeKind::Environment,
            1064 => OutcomeKind::Syntax,
            default => OutcomeKind::Contract,
        };
    }
}
