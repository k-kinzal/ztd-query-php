<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Domain\AbstractDomain;
use Deriver\Deriver\Evaluator\Intrinsic\PureIntrinsic;
use Deriver\Exception\InvalidInputException;

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
     * @param Registrations $contributions Trusted extension objects
     * @throws InvalidInputException If contracts or identities conflict
     */
    public function __construct(Registrations $contributions)
    {
        foreach ($contributions->intrinsics as $intrinsic) {
            $descriptor = $intrinsic->descriptor();
            if ($descriptor->id === '' || $descriptor->version === '' || $descriptor->operation === '' || $descriptor->arity < 0 || isset($this->intrinsics[$descriptor->operation], $this->manifest['intrinsic:' . $descriptor->id])) {
                throw new InvalidInputException('EVALUATOR_CONFLICT: invalid or repeated intrinsic identity.');
            }
            foreach ($descriptor->dependencies as $dependency) {
                if ($dependency < 0 || $dependency >= $descriptor->arity) {
                    throw new InvalidInputException('EVALUATOR_CONTRACT_VIOLATION: intrinsic dependency index.');
                }
            }
            if (isset($this->intrinsics[$descriptor->operation]) || isset($this->manifest['intrinsic:' . $descriptor->id])) {
                throw new InvalidInputException('EVALUATOR_CONFLICT: repeated intrinsic operation or ID.');
            }
            $this->intrinsics[$descriptor->operation] = $intrinsic;
            $this->manifest['intrinsic:' . $descriptor->id] = hash('sha256', serialize([$descriptor->version, $descriptor->operation, $descriptor->arity, $descriptor->dependencies]));
        }
        foreach ($contributions->domains as $domain) {
            [$id, $version] = (new \Deriver\Deriver\Evaluator\Domain\DomainOperations())->registration($domain);
            if ($id === '' || $version === '' || isset($this->domains[$id])) {
                throw new InvalidInputException('EVALUATOR_CONFLICT: invalid or repeated domain identity.');
            }
            $this->domains[$id] = $domain;
            $this->manifest['domain:' . $id] = $version;
        }
        ksort($this->manifest);
    }
}
