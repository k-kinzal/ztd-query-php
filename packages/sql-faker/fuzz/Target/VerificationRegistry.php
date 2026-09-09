<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Coverage\Verification\VerificationCoverage;

/**
 * Keeps one independent recorder per exact parser mode and SQL wrapper during a program campaign.
 */
final class VerificationRegistry
{
    /**
     * @var array<string, VerificationCoverage>
     */
    private array $recorders = [];

    /**
     * Retains the shared source denominator and campaign revision.
     */
    public function __construct(private readonly GrammarCoverage $grammar, private readonly string $revision, private readonly string $directory)
    {
    }

    /**
     * @param array<string, string> $configuration
     */
    public function forConfiguration(array $configuration): VerificationCoverage
    {
        ksort($configuration);
        $key = hash('sha256', serialize($configuration));
        return $this->recorders[$key] ??= new VerificationCoverage($this->grammar, $this->revision, $configuration, $this->directory);
    }

    /**
     * Checkpoints every mode at shutdown, including modes last visited before the periodic flush.
     */
    public function flush(): void
    {
        foreach ($this->recorders as $recorder) {
            $recorder->flush();
        }
    }
}
