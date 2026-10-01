<?php

declare(strict_types=1);

namespace Deriver\Result;

use Deriver\Reference\SourceRef;

/**
 * A typed edge in the explanation graph.
 *
 * @visibility public
 * @example Inspecting the contract
 *     $at = new \Deriver\Reference\SourceRef('s', 'a.php', 0, 1);
 *     (new \Deriver\Result\Derivation('e1', 'data', $at))->kind // => 'data'
 */
final class Derivation
{
    /**
     * @param string $id id
     * @param string $kind kind
     * @param SourceRef $source source
     * @param list<string> $parents parents
     * @param string $operation operation
     */
    public function __construct(
        public readonly string $id,
        public readonly string $kind,
        public readonly SourceRef $source,
        public readonly array $parents = [],
        public readonly string $operation = '',
    ) {
    }
}
