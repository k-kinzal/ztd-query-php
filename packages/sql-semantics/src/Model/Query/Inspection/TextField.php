<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection;

use SqlSemantics\Type\Nullability;

/**
 * Default facts for a text metadata field whose label is its enumeration value.
 * @visibility SqlSemantics
 */
trait TextField
{
    /**
     * Returns the enumeration value as the result label.
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Metadata fields are text unless a field declares otherwise.
     */
    public function type(): string
    {
        return 'varchar';
    }

    /**
     * Metadata fields are present unless a field declares otherwise.
     */
    public function nullability(): Nullability
    {
        return Nullability::NotNull;
    }
}
