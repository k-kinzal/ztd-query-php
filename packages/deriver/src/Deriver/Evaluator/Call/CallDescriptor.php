<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Declaration\Signature;

/**
 * Stable evaluator identity, target symbol, version, and explicit precedence.
 *
 * @visibility root
 * @example Inspecting the contract
 *     (new \Deriver\Deriver\Evaluator\Call\CallDescriptor('example', '1', 'Example\\key'))->version // => '1'
 */
final class CallDescriptor
{
    /**
     * @param string $id id
     * @param string $version version
     * @param string $symbol symbol
     * @param Signature $signature signature
     * @param int $priority priority
     * @param list<string> $replaces replaces
     * @param bool $replaceSource replaceSource
     * @param bool $useSourceSignature Bind the captured declaration's signature when present
     */
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly string $symbol,
        public readonly Signature $signature = new Signature(),
        public readonly int $priority = 0,
        public readonly array $replaces = [],
        public readonly bool $replaceSource = false,
        public readonly bool $useSourceSignature = false,
    ) {
    }
}
