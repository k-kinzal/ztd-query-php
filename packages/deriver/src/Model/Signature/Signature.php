<?php

declare(strict_types=1);

namespace Deriver\Model\Signature;

/**
 * An external callable signature with a declared return contract.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Model\Signature\Signature())->parameters // => []
 */
final class Signature
{
    /**
     * @param list<Parameter> $parameters parameters
     * @param string $returnType returnType
     * @param bool $allowExtraArguments Whether surplus positional arguments are legal
     * @param bool $byReference Whether the callable returns a shared reference cell
     */
    public function __construct(
        public readonly array $parameters = [],
        public readonly string $returnType = 'mixed',
        public readonly bool $allowExtraArguments = true,
        public readonly bool $byReference = false,
    ) {
    }
}
