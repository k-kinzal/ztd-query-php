<?php

declare(strict_types=1);

namespace Deriver\Deriver;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\SignatureIdentity;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Sources;

/**
 * Computes the identity of an analysis snapshot from its sources, evaluators, environment and target.
 * Prepared sources with equal identities answer every query identically; nothing else enters the identity.
 * @visibility Deriver
 * @visibility namespace
 */
final class SnapshotIdentity
{
    /**
     * Content identity of the whole snapshot.
     */
    public readonly string $id;
    /**
     * @var array<string, string> Source hashes by normalized path
     */
    public readonly array $sources;
    /**
     * @var array<string, string> Admission modes for files captured as declarations only
     */
    public readonly array $sourceModes;
    /**
     * @var array<string, string> Versioned evaluator, extension and state manifest
     */
    public readonly array $evaluators;
    /**
     * Content hash of explicitly supplied inputs, independent of top-level key order.
     */
    public readonly string $environmentHash;

    /**
     * @param Sources $input Effective captured sources
     * @param EvaluatorConfig $configuration Effective configuration
     * @param Evaluators $evaluators Registered evaluators
     * @param array<string, string> $providers Identities of providers already applied to the input
     */
    public function __construct(Sources $input, public readonly EvaluatorConfig $configuration, Evaluators $evaluators, array $providers = [])
    {
        $sources = [];
        $sourceModes = [];
        foreach ($input as $file) {
            $path = Path::normalize($file->path);
            $sources[$path] = hash('sha256', $file->contents);
            if ($file->declarationsOnly) {
                $sourceModes[$path] = 'declarations';
            }
        }
        ksort($sources);
        ksort($sourceModes);
        $manifest = [];
        foreach ($evaluators->manifest as $descriptor) {
            $manifest['evaluator:' . $descriptor->id] = hash('sha256', serialize([$descriptor->version, $descriptor->symbol, $descriptor->priority, $descriptor->replaces, $descriptor->replaceSource, $descriptor->useSourceSignature, (new SignatureIdentity())->key($descriptor->signature)]));
        }
        $manifest = [...$manifest, ...$evaluators->extensions->manifest, ...$evaluators->state->manifest, ...$providers];
        foreach ($configuration->evaluators as $evaluator) {
            $manifest['micro-evaluator:' . $evaluator->id] = hash('sha256', serialize([$evaluator->version, $evaluator->operation, $evaluator->name, $evaluator->priority, $evaluator->path, $evaluator->start, $evaluator->end]));
        }
        ksort($manifest);
        $environment = [];
        foreach ($configuration->environment as $key => $value) {
            $environment[$key] = (new Identity())->key($value);
        }
        ksort($environment);
        $this->environmentHash = hash('sha256', serialize($environment));
        $identity = [$sourceModes, $sources, $manifest, $configuration->target->id(), $this->environmentHash, $configuration->standardEvaluators, 'deriver-semantics-3'];
        $this->id = hash('sha256', serialize($identity));
        $this->sources = $sources;
        $this->sourceModes = $sourceModes;
        $this->evaluators = $manifest;
    }

    /**
     * Publishes the identity together with the diagnostics the declaration stage produced.
     * @param list<Frontier> $diagnostics Source diagnostics
     */
    public function snapshot(array $diagnostics): Candidate\Snapshot
    {
        return new Candidate\Snapshot($this->id, $this->sources, $this->evaluators, $this->configuration->target, $this->environmentHash, $diagnostics, $this->sourceModes);
    }
}
