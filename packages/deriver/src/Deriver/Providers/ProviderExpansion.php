<?php

declare(strict_types=1);

namespace Deriver\Deriver\Providers;

use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\InvalidInputException;

/**
 * Captures provider data once before constructing an immutable source snapshot.
 * @visibility root
 */
final class ProviderExpansion
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
    public readonly Sources $input;
    /**
     * @var array<string, Term> Captured symbolic environment.
     */
    public readonly array $environment;
    /**
     * Contributed evaluators, with the providers in stable order and the domains they add.
     */
    public readonly \Deriver\Deriver\Contributions $contributions;

    /**
     * @param Sources $input Original source snapshot
     * @param array<string, Term> $environment Explicit symbolic inputs
     * @param \Deriver\Deriver\Contributions $contributions Trusted explicit providers and evaluators
     * @throws InvalidInputException If provider identities conflict or environment keys overlap
     */
    public function __construct(Sources $input, array $environment, \Deriver\Deriver\Contributions $contributions = new \Deriver\Deriver\Contributions())
    {
        $files = iterator_to_array($input, false);
        $environment = [...$environment, ...$contributions->environment];
        $domains = $contributions->domains;
        $providers = [];
        foreach ($contributions->providers as $provider) {
            [$id, $version] = [$provider->id(), $provider->version()];
            if ($id === '' || $version === '' || isset($providers[$id])) {
                throw new InvalidInputException('EVALUATOR_CONFLICT: invalid or repeated provider identity.');
            }
            $providers[$id] = $provider;
            $this->manifest['provider:' . $id] = $version;
        }
        ksort($providers);
        ksort($this->manifest);
        foreach ($providers as $provider) {
            if ($provider instanceof DeclarationProvider) {
                array_push($files, ...$provider->declarations());
            }
            if ($provider instanceof EnvironmentProvider) {
                foreach ($provider->environment() as $key => $value) {
                    if (isset($environment[$key])) {
                        throw new InvalidInputException('EVALUATOR_CONFLICT: repeated environment key ' . $key);
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
        $this->input = new Sources($files);
        $this->environment = $environment;
        $this->contributions = new \Deriver\Deriver\Contributions($contributions->evaluators, array_values($providers), $domains, $contributions->intrinsics, $contributions->stateSlots, $environment);
    }
}
