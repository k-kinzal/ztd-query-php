<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Spatial;

use SqlSemantics\Model\Scalar\Value\Literal;

/**
 * Required spatial-system name and definition, with optional authority and description.
 * @visibility public
 * @example Reading supplied metadata without interpreting its string value
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'");
 *     $statement->definition->name->text // => "'Greek'"
 */
final class SpatialDefinition
{
    /**
     * The definition string is an operand for the consumer's spatial-type implementation.
     */
    public function __construct(
        public readonly Literal $name,
        public readonly Literal $definition,
        public readonly ?Organization $organization = null,
        public readonly ?Literal $description = null,
    ) {
        SpatialOperands::text($name, $definition, ...($description === null ? [] : [$description]));
    }
}
