<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Sources\SourceRef;

/**
 * Normalized metadata for selecting a declarative call evaluator.
 *
 * @visibility root
 * @example Inspecting the contract
 *     $call = new \Deriver\Deriver\Evaluator\Call\CallDescription('key', new \Deriver\Deriver\Evaluator\Declaration\Signature(), new \Deriver\Deriver\Evaluator\TargetProfile());
 *     $call->symbol // => 'key'
 */
final class CallDescription
{
    /**
     * Signature-normalized evaluator inputs.
     */
    public readonly ArgumentBindings $arguments;

    /**
     * @param string $symbol symbol
     * @param Signature $signature signature
     * @param TargetProfile $target target
     * @param string $receiverType receiverType
     * @param ArgumentBindings|null $arguments Evaluated bindings, or formal signature handles when omitted
     * @param SourceRef|null $source Source call site, shared by preparation and evaluated invocation
     * @param \Deriver\Deriver\Evaluator\Declaration\DeclarationLookup|null $declarations Captured parser-independent declaration metadata
     */
    public function __construct(
        public readonly string $symbol,
        public readonly Signature $signature,
        public readonly TargetProfile $target,
        public readonly string $receiverType = '',
        ?ArgumentBindings $arguments = null,
        public readonly ?SourceRef $source = null,
        public readonly ?\Deriver\Deriver\Evaluator\Declaration\DeclarationLookup $declarations = null,
    ) {
        $this->arguments = $arguments ?? ArgumentBindings::formal($signature);
    }
}
