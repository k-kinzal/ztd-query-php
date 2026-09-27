<?php

declare(strict_types=1);

namespace Deriver\Model;

use Deriver\Model\Binding\ArgumentBindings;
use Deriver\Model\Signature\Signature;
use Deriver\Project\TargetProfile;

/**
 * Normalized metadata for selecting a declarative call model.
 *
 * @visibility public
 * @example Inspecting the contract
 *     $call = new \Deriver\Model\CallDescription('key', new \Deriver\Model\Signature\Signature(), new \Deriver\Project\TargetProfile());
 *     $call->symbol // => 'key'
 */
final class CallDescription
{
    /**
     * Signature-normalized model inputs.
     */
    public readonly ArgumentBindings $arguments;

    /**
     * @param string $symbol symbol
     * @param Signature $signature signature
     * @param TargetProfile $target target
     * @param string $receiverType receiverType
     * @param ArgumentBindings|null $arguments Evaluated bindings, or formal signature handles when omitted
     * @param array<string, string> $dependencyVersions Explicit captured package versions
     */
    public function __construct(
        public readonly string $symbol,
        public readonly Signature $signature,
        public readonly TargetProfile $target,
        public readonly string $receiverType = '',
        ?ArgumentBindings $arguments = null,
        public readonly array $dependencyVersions = [],
    ) {
        $this->arguments = $arguments ?? ArgumentBindings::formal($signature);
    }
}
