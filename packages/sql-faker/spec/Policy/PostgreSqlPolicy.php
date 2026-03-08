<?php

declare(strict_types=1);

namespace Spec\Policy;

use Spec\Probe\ProbeResult;

/**
 * Interprets PostgreSQL SQLSTATEs for spec witnesses and groups them into
 * accepted, expected-environment, syntax, or contract outcomes.
 */
final class PostgreSqlPolicy implements OutcomePolicy
{
    /**
     * Returns the SQL dialect handled by this policy.
     */
    public function dialect(): string
    {
        return 'postgresql';
    }

    /**
     * Maps a normalized PostgreSQL probe result to the outcome category used by claims.
     */
    public function classify(ProbeResult $probeResult): OutcomeKind
    {
        if ($probeResult->accepted) {
            return OutcomeKind::Accepted;
        }

        $sqlState = $probeResult->sqlState;
        $message = $probeResult->message ?? '';
        if ($sqlState === null) {
            return OutcomeKind::Unknown;
        }

        if ($sqlState === '22023'
            && str_contains($message, 'role "')
            && str_contains($message, 'does not exist')) {
            return OutcomeKind::State;
        }

        if ($sqlState === '22023'
            && (str_contains($message, 'no security label providers have been loaded')
                || (str_contains($message, 'security label provider') && str_contains($message, 'not loaded')))) {
            return OutcomeKind::Environment;
        }

        if ($sqlState === '42P16'
            && (str_contains($message, 'cannot drop columns from view')
                || str_contains($message, 'cannot change name of view column'))) {
            return OutcomeKind::State;
        }

        if ($sqlState === '42P10'
            && str_contains($message, 'there is no unique or exclusion constraint matching the ON CONFLICT specification')) {
            return OutcomeKind::State;
        }

        if ($sqlState === '42P17'
            && str_contains($message, 'is not partitioned')) {
            return OutcomeKind::State;
        }

        if ($sqlState === '57014'
            && str_contains($message, 'statement timeout')) {
            return OutcomeKind::Resource;
        }

        return match ($sqlState) {
            '42704', '42P01', '42703', '3F000', '42883', '42P07', '42P06', '42P03', '42710', '25001', '25P01', '26000', '34000', '3B001', '2BP01', '3D000', '42809', '55000', '0LP01' => OutcomeKind::State,
            '58P01', '0A000' => OutcomeKind::Environment,
            '53200' => OutcomeKind::Resource,
            '42601' => OutcomeKind::Syntax,
            default => OutcomeKind::Contract,
        };
    }
}
