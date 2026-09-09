<?php

declare(strict_types=1);

namespace SqlFaker\Coverage\Verification;

use JsonException;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\CoverageSnapshotStore;
use SqlFaker\Coverage\GrammarCoverage;

/**
 * Persists independent parser verdicts under a key including the oracle revision and actual DB configuration.
 * Grammar reached/emitted measurements remain independent; only checked SQL can create a witness here.
 * @phpstan-import-type Trace from \SqlFaker\Coverage\GenerationTrace
 */
final class VerificationCoverage
{
    /**
     * @var array<string, VerificationEvent>
     */
    private array $events = [];
    /**
     * @var array{grammarFingerprint: string, inventoryDigest: string, generatorRevision: string, oracleRevision: string, configuration: array<string, string>}
     */
    private readonly array $identity;
    private readonly ?CoverageSnapshotStore $store;
    private readonly SqlContext $context;
    private readonly ?VerificationWitnessStore $witnessStore;
    private bool $dirty = false;
    private int $observations = 0;
    /**
     * @var array{inputHash: string, sqlHash: string, result: VerificationResult, trace: Trace}|null
     */
    private ?array $last = null;

    /**
     * Restores only this grammar/generator/oracle/configuration combination.
     * @param array<string, string> $configuration Actual engine version, parser mode and session settings
     */
    public function __construct(private readonly GrammarCoverage $grammar, string $oracleRevision, array $configuration, ?string $directory = null)
    {
        $this->context = new SqlContext($configuration['prefix'] ?? '', $configuration['suffix'] ?? '');
        $this->witnessStore = $directory === null ? null : new VerificationWitnessStore($directory . '/witnesses');
        ksort($configuration);
        $snapshot = $grammar->snapshot();
        $this->identity = ['grammarFingerprint' => $snapshot['grammarFingerprint'], 'inventoryDigest' => $snapshot['inventoryDigest'],
            'generatorRevision' => $snapshot['generatorRevision'], 'oracleRevision' => $oracleRevision, 'configuration' => $configuration];
        $key = hash('sha256', serialize([1, $this->identity]));
        $this->store = $directory === null ? null : new CoverageSnapshotStore($directory, $key);
        $json = $this->store?->read();
        if ($json !== null) {
            $this->merge($json);
        }
    }

    /**
     * Credits a completed generation only when its exact SQL was independently checked.
     * @throws CoverageException When the observation does not belong to the latest successful generation
     */
    public function record(string $sql, string $input, VerificationResult $result): void
    {
        $trace = $this->grammar->lastGeneration();
        $sqlHash = hash('sha256', $sql);
        $attempt = $trace === null ? null : ($trace['attempts'][count($trace['attempts']) - 1] ?? null);
        if ($trace === null || $trace['status'] !== 'success' || ($attempt['sqlHash'] ?? null) !== hash('sha256', $this->context->fragment($sql))) {
            throw new CoverageException('Verification does not match the latest generated SQL.');
        }
        $inputHash = hash('sha256', $input);
        $this->last = ['inputHash' => $inputHash, 'sqlHash' => $sqlHash, 'result' => $result, 'trace' => $trace];
        ++$this->observations;
        $previousCount = count($this->events);
        $groups = ['production' => $trace['emittedIds'], ...$trace['features']];
        foreach ($groups as $kind => $ids) {
            if (!in_array($kind, ['production', 'lexeme', 'compound', 'version-case', 'rewrite', 'spacing'], true)) {
                throw new CoverageException('Unknown verification feature kind: ' . $kind);
            }
            foreach ($ids as $id) {
                $event = new VerificationEvent($result->status, $kind, $id, $inputHash, $sqlHash, $result->code);
                $this->events[$event->key()] ??= $event;
            }
        }
        if (count($this->events) > $previousCount) {
            $this->witnessStore?->record($input, $sql);
        }
        $this->dirty = true;
    }

    /**
     * Reports all verdicts separately against the original unchanged production denominator.
     * @return array{formatVersion: int, identity: array{grammarFingerprint: string, inventoryDigest: string, generatorRevision: string, oracleRevision: string, configuration: array<string, string>}, denominatorIds: list<string>, coverage: array<string, array<string, list<string>>>, acceptedRate: float, witnesses: list<array{status: string, kind: string, id: string, inputHash: string, sqlHash: string, code: string}>, observationsInRun: int, last: array{inputHash: string, sqlHash: string, result: VerificationResult, trace: Trace}|null}
     */
    public function snapshot(): array
    {
        $coverage = [];
        foreach ($this->events as $event) {
            $coverage[$event->status][$event->kind][] = $event->id;
        }
        $denominator = $this->grammar->inventory()->denominator;
        $accepted = count(array_intersect($coverage['accepted']['production'] ?? [], $denominator));
        return ['formatVersion' => 1, 'identity' => $this->identity, 'denominatorIds' => $denominator, 'coverage' => $coverage,
            'acceptedRate' => $denominator === [] ? 0.0 : $accepted * 1.0 / count($denominator),
            'witnesses' => array_values(array_map(static fn (VerificationEvent $event): array => $event->toArray(), $this->events)),
            'observationsInRun' => $this->observations, 'last' => $this->last];
    }

    /**
     * Flushes bounded feature witnesses and the latest diagnostic trace atomically.
     * @throws CoverageException When the checkpoint cannot be encoded or persisted
     */
    public function flush(): void
    {
        if ($this->store === null || !$this->dirty) {
            return;
        }
        try {
            $this->store->write(json_encode($this->snapshot(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } catch (JsonException $exception) {
            throw new CoverageException('Cannot encode verification coverage.', 0, $exception);
        }
        $this->dirty = false;
    }

    /**
     * Ignores stored derived counters and reconstructs cumulative sets from validated witnesses.
     * @throws CoverageException When stored history is corrupt or belongs to a different oracle
     */
    public function merge(string $json): void
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CoverageException('Invalid verification coverage JSON.', 0, $exception);
        }
        if (!is_array($data) || ($data['formatVersion'] ?? null) !== 1 || ($data['identity'] ?? null) !== $this->identity
            || !is_array($data['witnesses'] ?? null) || !array_is_list($data['witnesses'])) {
            throw new CoverageException('Invalid or incompatible verification coverage.');
        }
        $events = [];
        foreach ($data['witnesses'] as $row) {
            $event = VerificationEvent::fromArray($row);
            if ($event->kind === 'production' && !isset($this->grammar->inventory()->entries[$event->id])) {
                throw new CoverageException('Verification contains an unknown production.');
            }
            $events[$event->key()] ??= $event;
        }
        $this->events += $events;
        $this->dirty = true;
    }
}
