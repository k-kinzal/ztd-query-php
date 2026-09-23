<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Spatial;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An authority name paired with its unsigned 32-bit coordinate-system identifier.
 * @visibility public
 * @example Inspecting the organization identity
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text' ORGANIZATION 'EPSG' IDENTIFIED BY 4120");
 *     $statement->definition->organization->identifier // => 4120
 */
final class Organization
{
    /**
     * Both authority operands are required whenever ORGANIZATION is present.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $name, public readonly int $identifier)
    {
        SpatialOperands::text($name);
        if ($identifier < 0 || $identifier > 4294967295) {
            throw new InvalidStructure('An organization coordinate-system identifier must be an unsigned 32-bit integer.');
        }
    }
}
