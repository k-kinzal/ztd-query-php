<?php

declare(strict_types=1);

namespace Deriver\Model;

use Deriver\Model\Signature\Signature;

/**
 * Stable model identity, target symbol, version, and explicit precedence.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Model\ModelDescriptor('example', '1', 'Example\\key'))->version // => '1'
 */
final class ModelDescriptor
{
    /**
     * @param string $id id
     * @param string $version version
     * @param string $symbol symbol
     * @param Signature $signature signature
     * @param int $priority priority
     * @param list<string> $replaces replaces
     * @param bool $replaceSource replaceSource
     */
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly string $symbol,
        public readonly Signature $signature = new Signature(),
        public readonly int $priority = 0,
        public readonly array $replaces = [],
        public readonly bool $replaceSource = false,
    ) {
    }
}
