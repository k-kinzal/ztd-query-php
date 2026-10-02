<?php

declare(strict_types=1);

namespace SqlSemantics\Lowering;

/**
 * The operand leaves one lowering created from source tokens.
 *
 * Names, literals and parameters are recorded when they are lowered. After
 * the statement is built, every recorded leaf must be reachable in it, so a
 * rule cannot read an operand and then drop it. The record lives for one
 * analysis and is not part of the published model.
 *
 * @visibility SqlSemantics
 */
final class Leaves
{
    /**
     * @var list<object>
     */
    private array $values = [];

    /**
     * Records a leaf value and returns it.
     *
     * @template T of object
     * @param T $value
     * @return T
     */
    public function record(object $value): object
    {
        $this->values[] = $value;

        return $value;
    }

    /**
     * Answers every recorded leaf.
     *
     * @return list<object>
     */
    public function all(): array
    {
        return $this->values;
    }
}
