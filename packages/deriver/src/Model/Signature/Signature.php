<?php

declare(strict_types=1);

namespace Deriver\Model\Signature;

/**
 * An external callable signature with a declared return contract.
 * Source signatures carry their raw doc comment; Deriver never reads PHPDoc types from it.
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
     * @param string $docComment Raw doc comment of the source declaration, or an empty string; it never affects analysis
     */
    public function __construct(
        public readonly array $parameters = [],
        public readonly string $returnType = 'mixed',
        public readonly bool $allowExtraArguments = true,
        public readonly bool $byReference = false,
        public readonly string $docComment = '',
    ) {
    }
}
