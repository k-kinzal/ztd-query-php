<?php

declare(strict_types=1);

namespace Deriver\Session;

use Deriver\Diagnostic\Frontier;
use Deriver\Model\Registration\ProviderExpansion;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\SignatureIdentity;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Result\Serialization\JsonText;
use Deriver\Value\Identity;
use JsonException;

/**
 * Computes the identity of a project snapshot from its sources, models, environment and target.
 * Two sessions with equal identities answer every query identically; nothing else enters the identity.
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
     * @var array<string, string> Versioned model, extension, state and expansion rule manifest
     */
    public readonly array $models;
    /**
     * @var array<string, string> Declared dependency versions
     */
    public readonly array $dependencies;

    /**
     * @param ProjectInput $input Effective captured sources
     * @param Configuration $configuration Effective configuration
     * @param Registry $models Registered models
     * @param ProviderExpansion $providers Provider contributions already applied to the input
     * @throws JsonException If the identity cannot be encoded
     */
    public function __construct(ProjectInput $input, public readonly Configuration $configuration, Registry $models, ProviderExpansion $providers)
    {
        $sources = [];
        $sourceModes = [];
        foreach ($input->files as $file) {
            $path = ProjectInput::normalize($file->path);
            $sources[$path] = hash('sha256', $file->contents);
            if ($file->declarationsOnly) {
                $sourceModes[$path] = 'declarations';
            }
        }
        ksort($sources);
        ksort($sourceModes);
        $manifest = [];
        foreach ($models->manifest as $descriptor) {
            $manifest['model:' . $descriptor->id] = hash('sha256', serialize([$descriptor->version, $descriptor->symbol, $descriptor->priority, $descriptor->replaces, $descriptor->replaceSource, $descriptor->useSourceSignature, (new SignatureIdentity())->key($descriptor->signature)]));
        }
        $manifest = [...$manifest, ...$models->extensions->manifest, ...$models->state->manifest, ...$providers->manifest];
        foreach ($configuration->expansionRules as $rule) {
            $manifest['expansion:' . $rule->id] = hash('sha256', serialize([$rule->version, $rule->operation, $rule->name, $rule->priority, $rule->path, $rule->start, $rule->end]));
        }
        ksort($manifest);
        $environment = [];
        foreach ($configuration->environment as $key => $value) {
            $environment[$key] = (new Identity())->key($value);
        }
        ksort($environment);
        $dependencies = $configuration->dependencyVersions;
        ksort($dependencies);
        $identity = [$sourceModes, $dependencies, $sources, $manifest, $configuration->target->id(), $configuration->closedWorld, $configuration->environmentVersion, $environment, $configuration->standardModels, 'deriver-semantics-2'];
        $this->id = hash('sha256', json_encode((new JsonText())->tree($identity), JSON_THROW_ON_ERROR));
        $this->sources = $sources;
        $this->sourceModes = $sourceModes;
        $this->models = $manifest;
        $this->dependencies = $dependencies;
    }

    /**
     * Publishes the identity together with the diagnostics the declaration stage produced.
     * @param list<Frontier> $diagnostics Source diagnostics
     */
    public function snapshot(array $diagnostics): ProjectSnapshot
    {
        return new ProjectSnapshot($this->id, $this->sources, $this->models, $this->configuration->target, $this->configuration->closedWorld, $this->configuration->environmentVersion, $diagnostics, $this->dependencies, $this->sourceModes);
    }
}
