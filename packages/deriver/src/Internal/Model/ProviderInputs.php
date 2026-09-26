<?php

declare(strict_types=1);

namespace Deriver\Internal\Model;

use Deriver\Api\InvalidInputException;
use Deriver\Api\Project\Configuration;
use Deriver\Api\Project\EntryPoint;
use Deriver\Api\Project\ProjectInput;
use Deriver\Model\Provider\DeclarationProvider;
use Deriver\Model\Provider\DomainProvider;
use Deriver\Model\Provider\EntryPointProvider;
use Deriver\Model\Provider\EnvironmentProvider;

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
        $boundary = new ModelBoundary();
        foreach ($configuration->providers as $provider) {
            [$id, $version] = $boundary->providerRegistration($provider);
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
                array_push($files, ...$boundary->declarations($provider)->files);
            }
            if ($provider instanceof EnvironmentProvider) {
                foreach ($boundary->environment($provider) as $key => $value) {
                    if (isset($environment[$key])) {
                        throw new InvalidInputException('MODEL_CONFLICT: repeated environment key ' . $key);
                    }
                    $environment[$key] = $value;
                }
            }
            if ($provider instanceof EntryPointProvider) {
                array_push($this->entries, ...$boundary->entries($provider));
            }
            if ($provider instanceof DomainProvider) {
                array_push($domains, ...$boundary->domains($provider));
            }
        }
        $this->input = new ProjectInput($files);
        $this->configuration = new Configuration($configuration->target, $configuration->models, $configuration->closedWorld, $environment, $configuration->environmentVersion, $configuration->standardModels, $configuration->intrinsics, $domains, array_values($providers), $configuration->resources, $configuration->dependencyVersions, $configuration->stateSlots, $configuration->sourceLimits);
    }
}
