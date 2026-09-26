<?php

declare(strict_types=1);

namespace Deriver\Internal\Model;

use Deriver\Api\InvalidInputException;
use Deriver\Api\Project\Configuration;
use Deriver\Model\Domain\AbstractDomain;
use Deriver\Model\Intrinsic\PureIntrinsic;

/**
 * Validates and versions explicitly installed domain and intrinsic contracts.
 * @visibility root
 */
final class Extensions
{
    /**
     * @var array<string, PureIntrinsic> Pure operations by declared name.
     */
    public array $intrinsics = [];
    /**
     * @var array<string, AbstractDomain> Abstract lattices by domain identity.
     */
    public array $domains = [];
    /**
     * @var array<string, string> Semantic extension versions and dependency contracts.
     */
    public array $manifest = [];

    /**
     * @param Configuration $configuration Trusted extension objects
     * @throws InvalidInputException If contracts or identities conflict
     */
    public function __construct(Configuration $configuration)
    {
        $boundary = new ModelBoundary();
        foreach ($configuration->intrinsics as $intrinsic) {
            $descriptor = $boundary->intrinsicDescriptor($intrinsic);
            if ($descriptor->id === '' || $descriptor->version === '' || $descriptor->operation === '' || $descriptor->arity < 0 || isset($this->intrinsics[$descriptor->operation], $this->manifest['intrinsic:' . $descriptor->id])) {
                throw new InvalidInputException('MODEL_CONFLICT: invalid or repeated intrinsic identity.');
            }
            foreach ($descriptor->dependencies as $dependency) {
                if ($dependency < 0 || $dependency >= $descriptor->arity) {
                    throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: intrinsic dependency index.');
                }
            }
            if (isset($this->intrinsics[$descriptor->operation]) || isset($this->manifest['intrinsic:' . $descriptor->id])) {
                throw new InvalidInputException('MODEL_CONFLICT: repeated intrinsic operation or ID.');
            }
            $this->intrinsics[$descriptor->operation] = $intrinsic;
            $this->manifest['intrinsic:' . $descriptor->id] = $descriptor->version . ':' . $descriptor->operation . ':' . $descriptor->arity . ':' . implode(',', $descriptor->dependencies);
        }
        foreach ($configuration->domains as $domain) {
            [$id, $version] = $boundary->domainRegistration($domain);
            if ($id === '' || $version === '' || isset($this->domains[$id])) {
                throw new InvalidInputException('MODEL_CONFLICT: invalid or repeated domain identity.');
            }
            $this->domains[$id] = $domain;
            $this->manifest['domain:' . $id] = $version;
        }
        ksort($this->manifest);
    }
}
