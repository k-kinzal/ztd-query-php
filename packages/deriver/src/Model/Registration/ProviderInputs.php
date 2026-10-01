<?php

declare(strict_types=1);

namespace Deriver\Model\Registration;

use Deriver\Exception\InvalidInputException;
use Deriver\Model\Provider\DeclarationProvider;
use Deriver\Model\Provider\DomainProvider;
use Deriver\Model\Provider\EntryPointProvider;
use Deriver\Model\Provider\EnvironmentProvider;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;

/**
 * Captures provider data once before constructing an immutable source snapshot.
 * @visibility root
 */
final class ProviderInputs
{
    /**
     * @var array<string, string> Stable provider manifest.
     */
    public array $manifest = [];
    /**
     * @var list<EntryPoint> Explicit contributed entries.
     */
    public array $entries = [];
    /**
     * Captured sources and supplied declarations.
     */
    public readonly ProjectInput $input;
    /**
     * Captured environment and registered domains.
     */
    public readonly Configuration $configuration;

    /**
     * @param ProjectInput $input Original source snapshot
     * @param Configuration $configuration Trusted explicit providers
     * @throws InvalidInputException If provider identities conflict or environment keys overlap
     */
    public function __construct(ProjectInput $input, Configuration $configuration)
    {
        $files = $input->files;
        $environment = $configuration->environment;
        $domains = $configuration->domains;
        $providers = [];
        foreach ($configuration->providers as $provider) {
            [$id, $version] = [$provider->id(), $provider->version()];
            if ($id === '' || $version === '' || isset($providers[$id])) {
                throw new InvalidInputException('MODEL_CONFLICT: invalid or repeated provider identity.');
            }
            $providers[$id] = $provider;
            $this->manifest['provider:' . $id] = $version;
        }
        ksort($providers);
        ksort($this->manifest);
        foreach ($providers as $provider) {
            if ($provider instanceof DeclarationProvider) {
                array_push($files, ...$provider->declarations()->files);
            }
            if ($provider instanceof EnvironmentProvider) {
                foreach ($provider->environment() as $key => $value) {
                    if (isset($environment[$key])) {
                        throw new InvalidInputException('MODEL_CONFLICT: repeated environment key ' . $key);
                    }
                    $environment[$key] = $value;
                }
            }
            if ($provider instanceof EntryPointProvider) {
                array_push($this->entries, ...$provider->entries());
            }
            if ($provider instanceof DomainProvider) {
                array_push($domains, ...$provider->domains());
            }
        }
        $this->input = new ProjectInput($files);
        $this->configuration = new Configuration($configuration->target, $configuration->models, $configuration->closedWorld, $environment, $configuration->environmentVersion, $configuration->standardModels, $configuration->intrinsics, $domains, array_values($providers), $configuration->resources, $configuration->dependencyVersions, $configuration->stateSlots, $configuration->sourceLimits);
    }
}
