<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Enumeration;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The existing label next to which a new enum label is inserted.
 * @visibility public
 * @example Reading the position of a new label
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood ADD VALUE 'calm' AFTER 'happy'");
 *     $statement->position->placement // => \SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabelPlacement::After
 *     $statement->position->neighbor // => 'happy'
 */
final class EnumLabelPosition
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly EnumLabelPlacement $placement, public readonly string $neighbor)
    {
        EnumLabels::label($neighbor);
    }
}
