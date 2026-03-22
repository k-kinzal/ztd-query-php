<?php

declare(strict_types=1);

namespace Spec\Policy;

use Spec\Probe\ProbeResult;

/**
 * Interprets SQLite prepare errors for spec witnesses and separates expected
 * missing-schema outcomes from syntax and contract failures.
 */
final class SqlitePolicy implements OutcomePolicy
{
    /**
     * Returns the SQL dialect handled by this policy.
     */
    public function dialect(): string
    {
        return 'sqlite';
    }

    /**
     * Maps a normalized SQLite probe result to the outcome category used by claims.
     */
    public function classify(ProbeResult $probeResult): OutcomeKind
    {
        if ($probeResult->accepted) {
            return OutcomeKind::Accepted;
        }

        $message = $probeResult->message;
        if ($message === null || $message === 'PDO::prepare returned false') {
            return OutcomeKind::Unknown;
        }

        return match (true) {
            str_contains($message, 'no such table:'),
            str_contains($message, 'unknown database'),
            str_contains($message, 'no such view:'),
            str_contains($message, 'no such index:'),
            str_contains($message, 'no such column:'),
            str_contains($message, 'no such function:'),
            str_contains($message, 'no such trigger:'),
            str_contains($message, 'no such collation sequence:'),
            str_contains($message, 'unable to identify the object to be reindexed') => OutcomeKind::State,
            str_contains($message, 'disk I/O error'),
            str_contains($message, 'out of memory') => OutcomeKind::Resource,
            str_contains($message, 'syntax error'),
            str_contains($message, 'incomplete input') => OutcomeKind::Syntax,
            default => OutcomeKind::Contract,
        };
    }
}
