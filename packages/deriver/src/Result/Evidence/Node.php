<?php

declare(strict_types=1);

namespace Deriver\Result\Evidence;

use Deriver\Reference\SourceRef;

/**
 * An immutable derivation node. Inputs are AND unless kind is choice/context-or.
 * @example Identifying a shared derivation
 *     $node = new \Deriver\Result\Evidence\Node('operation', attributes: ['operation' => '+']);
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
        public readonly string $kind,
        public readonly array $inputs = [],
        public readonly ?SourceRef $source = null,
        public readonly array $attributes = [],
    ) {
        $ids = array_map(static fn (self $input): string => $input->id, $inputs);
        $this->id = hash('sha256', serialize([$kind, $ids, $source?->id(), $attributes]));
    }
}
