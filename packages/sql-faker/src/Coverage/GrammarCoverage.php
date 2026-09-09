<?php

declare(strict_types=1);

namespace SqlFaker\Coverage;

use JsonException;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Observes derivations without influencing generation or consuming randomness.
 *
 * @phpstan-import-type Measurement from CoverageSets
 * @phpstan-import-type Trace from GenerationTrace
 * @phpstan-import-type Entry from GrammarCoverageInventory
 * @phpstan-type Checkpoint array{savedAt: string, runId: string, generationsObservedInRun: int, generationInProgress: bool}
 * @phpstan-type History array{formatVersion: int, grammarFingerprint: string, generatorRevision: string, root: string, inventoryDigest: string, cumulative: array{reachedIds: list<string>, emittedIds: list<string>}, checkpoint: Checkpoint}
 * @phpstan-type Snapshot array{formatVersion: int, grammarFingerprint: string, generatorRevision: string, root: string, inventoryDigest: string, denominatorIds: list<string>, inventory: array<string, Entry>, adaptations: list<array{origin: string, status: string}>, current: Measurement, cumulative: Measurement, checkpoint: Checkpoint, restoredCheckpoint: Checkpoint|null}
 * @visibility public
 * @example Begin memory-only grammar measurement
 *     $coverage = new \SqlFaker\Coverage\GrammarCoverage();
 *     $coverage->lastGeneration() // => null
 */
final class GrammarCoverage
{
    private ?GrammarCoverageInventory $inventory = null;
    private string $revision = '';
    private CoverageSets $saved;
    private CoverageSets $current;
    private ?GenerationTrace $trace = null;
    private ?CoverageSnapshotStore $store = null;
    private bool $dirty = false;
    private bool $inProgress = false;
    private int $generations = 0;
    private readonly string $runId;
    /**
     * @var Checkpoint|null
     */
    private ?array $restoredCheckpoint = null;

    /**
     * Enables memory-only measurement unless a snapshot directory is explicitly supplied.
     */
    public function __construct(private readonly ?string $storageDirectory = null)
    {
        $this->saved = new CoverageSets();
        $this->current = new CoverageSets();
        $this->runId = bin2hex(random_bytes(16));
    }

    /**
     * Registers the full inventory and restores compatible history exactly once.
     *
     * @throws CoverageException When already registered to an incompatible generator
     */
    public function register(GrammarCoverageInventory $inventory, string $revision): void
    {
        if ($this->inventory !== null) {
            if ($this->inventory->digest !== $inventory->digest || $this->revision !== $revision) {
                throw new CoverageException('Coverage is already attached to a different generator.');
            }
            return;
        }
        $this->inventory = $inventory;
        $this->revision = $revision;
        try {
            $this->restore();
        } catch (CoverageException $failure) {
            $this->inventory = null;
            $this->revision = '';
            $this->store = null;
            $this->saved = new CoverageSets();
            throw $failure;
        }
    }

    /**
     * Restores only after compatibility identity is known; failed setup remains retryable.
     *
     * @throws CoverageException When history is corrupt, incompatible or inaccessible
     * @visibility root
     */
    public function restore(): void
    {
        if ($this->storageDirectory === null) {
            return;
        }
        $inventory = $this->inventory();
        $key = hash('sha256', '1:' . $inventory->fingerprint . ':' . $this->revision);
        $this->store = new CoverageSnapshotStore($this->storageDirectory, $key);
        $json = $this->store->read();
        if ($json !== null) {
            $history = (new SnapshotValidation())->decode($json);
            $this->merge($history);
            $this->restoredCheckpoint = $history['checkpoint'];
            $this->dirty = false;
        }
    }

    /**
     * Returns the full denominator, including alternatives never observed.
     *
     * @throws CoverageException When not attached to a provider
     */
    public function inventory(): GrammarCoverageInventory
    {
        return $this->inventory ?? throw new CoverageException('Attach coverage to a provider before reading it.');
    }

    /**
     * Begins a generation and replaces even the previous successful trace.
     *
     * @param array<string, int|string|bool|null> $planSummary
     */
    public function beginGeneration(string $root, array $planSummary): void
    {
        $this->trace = new GenerationTrace(++$this->generations, $root, $planSummary);
        $this->inProgress = true;
    }

    /**
     * Begins one lexical realization attempt.
     */
    public function beginAttempt(int $id): void
    {
        $this->trace?->beginAttempt($id);
    }

    /**
     * Records an occurrence immediately after production selection.
     */
    public function record(int $node, ?int $parent, ?int $position, string $rule, string $production, string $reason): void
    {
        if (!isset($this->saved->reached[$production]) && !isset($this->current->reached[$production])) {
            $this->dirty = true;
        }
        $this->current->reached[$production] = true;
        $this->trace?->record($node, $parent, $position, $rule, $production, $reason);
    }

    /**
     * Records original grammar choices even when a rewrite replaces their entire output.
     */
    public function recordSequence(TerminalSequence $sequence): void
    {
        $inventory = $this->inventory();
        foreach ($sequence->productions as $occurrence) {
            $production = $inventory->grammar->ruleMap[$occurrence->rule]->alternatives[$occurrence->ordinal];
            $this->record(
                $occurrence->id,
                $occurrence->parent,
                null,
                $occurrence->rule,
                $inventory->id($occurrence->rule, $production, $occurrence->ordinal),
                'selected'
            );
        }
        if ($this->trace !== null) {
            $this->trace->value['rewrites'] = $sequence->rewrites;
        }
    }

    /**
     * Exposes chosen compound candidates and the boundary rules used in the final output.
     */
    public function recordOutput(ResolvedOutput $output): void
    {
        if ($this->trace === null) {
            return;
        }
        $this->trace->value['lexicalEvents'] = array_map(static fn ($candidate): string => $candidate->id, $output->candidates);
        $this->trace->value['spacingEvents'] = array_map(static fn ($part): array =>
            ['candidate' => $part->candidate, 'separator' => $part->separator, 'rules' => $part->spacingRules], $output->parts);
    }

    /**
     * Keeps failed paths reached without treating them as SQL output.
     */
    public function discardAttempt(string $error): void
    {
        $this->trace?->discard($error);
    }

    /**
     * Adds only preserved source productions from the successful attempt to emitted coverage.
     * @param list<int>|null $nodes Preserved occurrence IDs, or null for an unmodified derivation
     */
    public function commitAttempt(string $sqlHash, ?array $nodes = null): void
    {
        foreach ($this->trace?->commit($sqlHash, $nodes) ?? [] as $id) {
            if (!isset($this->saved->emitted[$id]) && !isset($this->current->emitted[$id])) {
                $this->dirty = true;
            }
            $this->current->emitted[$id] = true;
        }
    }

    /**
     * Closes the generation in a finally block, retaining failures.
     */
    public function endGeneration(): void
    {
        $this->trace?->end();
        $this->inProgress = false;
    }

    /**
     * Returns the latest generation only; no full-run trace is accumulated.
     *
     * @return Trace|null
     */
    public function lastGeneration(): ?array
    {
        return $this->trace?->value;
    }

    /**
     * Reads named current and cumulative sets without performing file I/O.
     *
     * @return Snapshot
     */
    public function snapshot(): array
    {
        $inventory = $this->inventory();
        $cumulative = clone $this->saved;
        $cumulative->include($this->current);
        return ['formatVersion' => 1, 'grammarFingerprint' => $inventory->fingerprint,
            'generatorRevision' => $this->revision, 'root' => $inventory->root, 'inventoryDigest' => $inventory->digest,
            'denominatorIds' => $inventory->denominator, 'inventory' => $inventory->entries,
            'adaptations' => $inventory->adaptations, 'restoredCheckpoint' => $this->restoredCheckpoint,
            'current' => $this->current->measurement($inventory->denominator),
            'cumulative' => $cumulative->measurement($inventory->denominator),
            'checkpoint' => ['savedAt' => gmdate('c'), 'runId' => $this->runId,
                'generationsObservedInRun' => $this->generations, 'generationInProgress' => $this->inProgress]];
    }

    /**
     * Unions compatible cumulative history without counting it as current observation.
     *
     * @param History $snapshot
     * @throws CoverageException When identity, inventory or saved IDs are invalid
     */
    public function merge(array $snapshot): void
    {
        (new SnapshotValidation())->compatible($snapshot, $this->snapshot(), $this->inventory());
        $reached = array_fill_keys($snapshot['cumulative']['reachedIds'], true);
        $emitted = array_fill_keys($snapshot['cumulative']['emittedIds'], true);
        $this->dirty = $this->dirty || array_diff_key($reached, $this->saved->reached) !== []
            || array_diff_key($emitted, $this->saved->emitted) !== [];
        $this->saved->reached += $reached;
        $this->saved->emitted += $emitted;
    }

    /**
     * Saves dirty cumulative sets without clearing this run's observations.
     *
     * @throws CoverageException When a snapshot cannot be encoded
     */
    public function flush(): void
    {
        if ($this->store === null || !$this->dirty) {
            return;
        }
        try {
            $snapshot = $this->snapshot();
            unset($snapshot['current'], $snapshot['inventory'], $snapshot['adaptations'],
                $snapshot['denominatorIds'], $snapshot['restoredCheckpoint']);
            $snapshot['cumulative'] = ['reachedIds' => $snapshot['cumulative']['reachedIds'],
                'emittedIds' => $snapshot['cumulative']['emittedIds']];
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (JsonException $exception) {
            throw new CoverageException('Cannot encode coverage snapshot.', 0, $exception);
        }
        $this->store->write($json);
        $this->saved->include($this->current);
        $this->dirty = false;
    }

    /**
     * Clears current observations only; flush first to retain unsaved discoveries.
     */
    public function reset(): void
    {
        $this->current = new CoverageSets();
        $this->trace = null;
        $this->generations = 0;
        $this->inProgress = false;
    }
}
