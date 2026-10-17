<?php

declare(strict_types=1);

namespace Deriver\Model\Registration;

use Deriver\Model\CallModel;
use Deriver\Model\Domain\AbstractDomain;
use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Model\Provider\Provider;
use Deriver\Model\State\StateSlot;

/**
 * The models, providers, domains, intrinsics and state slots contributed to one session.
 * This is the input of the registration stage. It is not part of the public API: callers configure
 * a session with Configuration, and the library and its tests contribute models through this class.
 * @visibility root
 */
final class Contributions
{
    /**
     * @param list<CallModel> $models Trusted call models
     * @param list<Provider> $providers Captured project and dispatch contracts
     * @param list<AbstractDomain> $domains Registered abstract lattices
     * @param list<PureIntrinsic> $intrinsics Pure domain-specific operations
     * @param list<StateSlot> $stateSlots Abstract state initialization and lifecycle contracts
     */
    public function __construct(
        public readonly array $models = [],
        public readonly array $providers = [],
        public readonly array $domains = [],
        public readonly array $intrinsics = [],
        public readonly array $stateSlots = [],
    ) {
    }
}
