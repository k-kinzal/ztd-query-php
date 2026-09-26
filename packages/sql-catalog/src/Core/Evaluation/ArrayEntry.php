<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Evaluation;

/**
 * One element of a statically reconstructed array.
 *
 * @visibility root
 */
final class ArrayEntry
{
    /**
     * @param Domain|null $key The key, or null when the element takes the next integer key
     * @param Domain $value The element value
     */
    public function __construct(
        public readonly ?Domain $key,
        public readonly Domain $value,
    ) {
    }

    /**
     * The key when it resolved to a single scalar, otherwise null.
     */
    public function scalarKey(): string|int|null
    {
        $literal = $this->key?->soleLiteral();
        if ($literal === null) {
            return null;
        }
        if (is_int($literal->value)) {
            return $literal->value;
        }

        return is_string($literal->value) ? $literal->value : null;
    }

    /**
     * A canonical string used to deduplicate arrays.
     */
    public function signature(): string
    {
        return ($this->key?->signature() ?? '_') . '=>' . $this->value->signature();
    }
}
