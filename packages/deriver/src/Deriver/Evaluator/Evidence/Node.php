<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Evidence;

use Deriver\Deriver\Sources\SourceRef;

/**
 * An immutable derivation node. Inputs are AND unless kind is choice/context-or.
 * @example Identifying a shared derivation
 *     $node = new \Deriver\Deriver\Evaluator\Evidence\Node(\Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Operation, attributes: ['operation' => '+']);
 *     strlen($node->id) // => 64
 * @visibility public
 */
final class Node
{
    /**
     * Stable content identity over inputs, source and structured attributes.
     */
    public readonly string $id;

    /**
     * @param array<string, self> $inputs Role-labelled dependencies
     * @param array<string, scalar|null> $attributes Facts used by this expansion
     */
    public function __construct(
        public readonly EvidenceKind $kind,
        public readonly array $inputs = [],
        public readonly ?SourceRef $source = null,
        public readonly array $attributes = [],
    ) {
        $ids = array_map(static fn (self $input): string => $input->id, $inputs);
        $this->id = hash('sha256', serialize([$kind->value, $ids, $source?->id(), $attributes]));
    }
}
