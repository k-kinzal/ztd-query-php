<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Run;

use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Fuzz\Target\InfrastructureFailure;
use SqlFaker\Fuzz\Target\SyntaxFailure;
use SqlFaker\Fuzz\Target\VerificationResult;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\LexicalException;

/**
 * Owns run diagnostics and periodic persistence, independently of the coverage recorder.
 */
final class FuzzCheckpoint
{
    /**
     * @var array<string, int>
     */
    private array $counts = [];
    private int $calls = 0;
    private int $exitCode = 0;
    private float $lastSave;

    /**
     * @param array<string, int|string> $metadata
     */
    public function __construct(
        private readonly GrammarCoverage $coverage,
        private readonly string $directory,
        private readonly array $metadata,
        private readonly int $interval = 100,
        private readonly int $seconds = 30,
        private readonly int $initialInputs = 0
    ) {
        $this->lastSave = microtime(true);
    }

    /**
     * Counts acceptance separately from expected rejection and unsupported verification.
     */
    public function observe(VerificationResult $result, string $input, string $sql): void
    {
        $this->counts[$result->value] = ($this->counts[$result->value] ?? 0) + 1;
        if ($result === VerificationResult::Incomplete) {
            $this->write('last-incomplete.json', ['inputHex' => bin2hex($input), 'sql' => $sql]);
        }
        if (++$this->calls % $this->interval === 0 || microtime(true) - $this->lastSave >= $this->seconds) {
            $this->flush();
        }
    }

    /**
     * Preserves the original finding even if saving its checkpoint also fails.
     */
    public function failure(
        string $input,
        ?string $sql,
        GenerationException|LexicalException|SyntaxFailure|InfrastructureFailure $failure
    ): void {
        $this->exitCode = max($this->exitCode, $failure instanceof InfrastructureFailure ? 2 : 1);
        $this->saveFailure($input, $sql, $failure::class . ': ' . $failure->getMessage());
    }

    /**
     * Preserves unexpected engine failures without catching or suppressing them.
     */
    public function interrupted(string $input, ?string $sql): void
    {
        $this->exitCode = max($this->exitCode, 1);
        $this->saveFailure($input, $sql, 'Target interrupted; see PHP-Fuzzer diagnostics for the original failure.');
    }

    /**
     * Saves reproduction data while keeping persistence failures secondary.
     */
    public function saveFailure(string $input, ?string $sql, string $error): void
    {
        try {
            if (@file_put_contents($this->directory . '/failure.bin', $input) !== strlen($input)) {
                throw new CoverageException('Cannot save original failure input.');
            }
            $this->write('last-failure.json', ['inputHex' => bin2hex($input), 'sql' => $sql, 'error' => $error]);
            $this->flush();
        } catch (CoverageException $storageFailure) {
            fwrite(STDERR, 'Coverage checkpoint also failed: ' . $storageFailure->getMessage() . "\n");
        }
    }

    /**
     * Reports findings independently of PHP-Fuzzer 0.0.11's zero CLI exit status.
     */
    public function exitCode(): int
    {
        return $this->exitCode;
    }

    /**
     * Flushes at an explicit checkpoint or normal process shutdown.
     *
     * @throws CoverageException When measurement persistence fails
     */
    public function flush(): void
    {
        $this->coverage->flush();
        $this->write('run.json', []);
        $this->lastSave = microtime(true);
    }

    /**
     * Writes diagnostics outside the raw-input corpus.
     *
     * @param array<string, string|null> $details
     * @throws CoverageException When the diagnostic file cannot be written
     */
    public function write(string $filename, array $details): void
    {
        $json = json_encode(['metadata' => $this->metadata, 'verification' => $this->counts,
            'corpusReplay' => ['expectedInputs' => $this->initialInputs, 'completedInputs' => min($this->calls, $this->initialInputs),
                'complete' => $this->calls >= $this->initialInputs],
            'coverage' => $this->coverage->snapshot(), 'lastGeneration' => $this->coverage->lastGeneration(),
            'details' => $details], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || @file_put_contents($this->directory . '/' . $filename, $json) === false) {
            throw new CoverageException('Cannot save fuzz diagnostics: ' . $filename);
        }
    }
}
