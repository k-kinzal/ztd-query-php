<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Trace;

use Deriver\Deriver\Sources\SourceRef;

/**
 * A typed edge in the explanation graph.
 *
 * @visibility root
 * @example Inspecting the contract
 *     $at = new \Deriver\Deriver\Sources\SourceRef('s', 'a.php', 0, 1);
 *     (new \Deriver\Deriver\Evaluator\Execution\Trace\Derivation('e1', \Deriver\Deriver\Evaluator\Execution\Trace\DerivationKind::Data, $at))->kind === \Deriver\Deriver\Evaluator\Execution\Trace\DerivationKind::Data // => true
 */
final class Derivation
{
    /**
     * @param string $id id
     * @param DerivationKind $kind kind
     * @param SourceRef $source source
     * @param list<string> $parents parents
     * @param string $operation operation
     */
    public function __construct(
        public readonly string $id,
        public readonly DerivationKind $kind,
        public readonly SourceRef $source,
        public readonly array $parents = [],
        public readonly string $operation = '',
    ) {
    }
}
