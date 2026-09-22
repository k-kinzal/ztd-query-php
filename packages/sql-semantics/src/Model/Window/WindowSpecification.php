<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public

 */
final class WindowSpecification implements Window
{
    /**
     * @param list<\SqlSemantics\Model\Expression> $partitionBy
     * @param list<\SqlSemantics\Model\Ordering> $orderBy
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly ?string $base,
        public readonly array $partitionBy,
        public readonly array $orderBy,
        public readonly ?Frame $frame
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($partitionBy, \SqlSemantics\Model\Expression::class);
        \SqlSemantics\Model\Validation\Collections::objects($orderBy, \SqlSemantics\Model\Ordering::class);
        foreach ($orderBy as $order) {
            if (!$order->key instanceof \SqlSemantics\Model\Expression) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('This ordering requires an input expression, not a result alias or position.');
            }
        }
    }
    public function expressions(): array
    {
        return [...$this->partitionBy, ...array_map(static fn (\SqlSemantics\Model\Ordering $order): \SqlSemantics\Model\Expression => $order->key instanceof \SqlSemantics\Model\Expression ? $order->key : throw new \SqlSemantics\Model\Validation\InvalidStructure('This ordering is evaluated before projection and requires an expression.'), $this->orderBy), ...($this->frame === null ? [] : [...$this->frame->start->expressions(), ...$this->frame->end->expressions()])];
    }
}
