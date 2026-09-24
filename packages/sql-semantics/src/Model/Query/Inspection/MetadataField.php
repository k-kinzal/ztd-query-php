<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection;

use SqlSemantics\Type\Nullability;

/**
 * A result field of a metadata inspection, declared by a closed per-statement domain.
 * @visibility public
 * @example Reading the declared facts of a field
 *     $field = \SqlSemantics\Model\Query\Inspection\Field\Schema\CollationField::Id;
 *     [$field->label(), $field->type(), $field->nullability()->value] // => ['Id', 'bigint', 'not-null']
 */
interface MetadataField
{
    /**
     * Returns the result label the server uses for this field.
     */
    public function label(): string;

    /**
     * Returns the MySQL builtin type name of the field.
     */
    public function type(): string;

    /**
     * Returns the NULL fact declared for this field.
     */
    public function nullability(): Nullability;
}
