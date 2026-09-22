<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

/**
 * A one-based ordinal within the current JSON path's row sequence.
 * @visibility public
 */
final class Ordinality implements Column
{
    /**
     * Declares a generated ordinal column, which has no value-path expression.
     */
    public function __construct(public readonly string $name)
    {
    }
}
