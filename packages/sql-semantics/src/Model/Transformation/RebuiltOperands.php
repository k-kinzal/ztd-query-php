<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transformation;

use SqlSemantics\Model\Validation\InvalidStructure;
use WeakMap;

/**
 * Preserves shared operand identities during one immutable transformation.
 * @visibility SqlSemantics
 */
final class RebuiltOperands
{
    /**
     * @var WeakMap<object, object>
     */
    private readonly WeakMap $replacements;

    /**
     * Starts an independent transformation's identity map.
     */
    public function __construct()
    {
        $this->replacements = new WeakMap();
    }

    /**
     * @template T of object
     * @param T $original
     * @return T|null
     * @throws InvalidStructure
     */
    public function find(object $original): ?object
    {
        $replacement = $this->replacements[$original] ?? null;
        $class = $original::class;
        if ($replacement !== null && !$replacement instanceof $class) {
            throw new InvalidStructure('An immutable replacement must retain its semantic type.');
        }
        return $replacement;
    }

    /**
     * @template T of object
     * @param T $original
     * @param T $replacement
     * @return T
     */
    public function remember(object $original, object $replacement): object
    {
        $this->replacements[$original] = $replacement;
        return $replacement;
    }
}
