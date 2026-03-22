<?php

declare(strict_types=1);

namespace Fuzz\Policy;

use Fuzz\Probe\ProbeResult;

/**
 * Interprets SQLite prepare errors for fuzzing and allows failures caused by
 * missing schema objects or limited runtime resources, while surfacing syntax
 * errors and unknown diagnostics as bugs.
 */
final class SqliteFuzzPolicy implements FuzzPolicy
{
    /** @var array<string, string> */
    private const STATE_MESSAGE_FRAGMENTS = [
        'no such table:' => 'no such table',
        'unknown database' => 'unknown database',
        'no such view:' => 'no such view',
        'no such index:' => 'no such index',
        'no such column:' => 'no such column',
        'no such function:' => 'no such function',
        'no such trigger:' => 'no such trigger',
        'no such collation sequence:' => 'no such collation sequence',
        'unable to identify the object to be reindexed' => 'unable to identify the object to be reindexed',
    ];

    /** @var array<string, string> */
    private const RESOURCE_MESSAGE_FRAGMENTS = [
        'disk I/O error' => 'disk I/O error',
        'out of memory' => 'out of memory',
    ];

    /** @var array<string, string> */
    private const SYNTAX_MESSAGE_FRAGMENTS = [
        'syntax error' => 'syntax error',
        'incomplete input' => 'incomplete input',
    ];

    /**
     * Returns the SQL dialect handled by this policy.
     */
    public function dialect(): string
    {
        return 'sqlite';
    }

    /**
     * Classifies the normalized SQLite error message into an expected rejection
     * or a crash-worthy bug candidate.
     */
    public function classify(ProbeResult $probeResult): FuzzDecision
    {
        if ($probeResult->accepted) {
            return FuzzDecision::ignore('accepted', 'statement prepared successfully');
        }

        $message = $probeResult->message;
        if ($message === null) {
            return FuzzDecision::crash('unknown', 'SQLite did not provide an error message');
        }

        if ($message === 'PDO::prepare returned false') {
            return FuzzDecision::crash('unknown', 'PDO::prepare returned false');
        }

        $stateDecision = $this->matchMessageFragments($message, self::STATE_MESSAGE_FRAGMENTS, 'state', false);
        if ($stateDecision !== null) {
            return $stateDecision;
        }

        $resourceDecision = $this->matchMessageFragments($message, self::RESOURCE_MESSAGE_FRAGMENTS, 'resource', false);
        if ($resourceDecision !== null) {
            return $resourceDecision;
        }

        $syntaxDecision = $this->matchMessageFragments($message, self::SYNTAX_MESSAGE_FRAGMENTS, 'syntax', true);
        if ($syntaxDecision !== null) {
            return $syntaxDecision;
        }

        return FuzzDecision::crash('contract', sprintf('Unhandled SQLite error: %s', $message));
    }

    /**
     * @param array<string, string> $fragments
     */
    private function matchMessageFragments(
        string $message,
        array $fragments,
        string $classification,
        bool $shouldCrash,
    ): ?FuzzDecision {
        foreach ($fragments as $fragment => $reason) {
            if (!str_contains($message, $fragment)) {
                continue;
            }

            if ($shouldCrash) {
                return FuzzDecision::crash($classification, $reason);
            }

            return FuzzDecision::ignore($classification, $reason);
        };

        return null;
    }
}
