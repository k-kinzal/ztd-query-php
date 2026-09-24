<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database\PostgreSql;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * Validates the storage parameters a PostgreSQL tablespace defines.
 * @visibility SqlSemantics
 */
final class TablespaceInvariant
{
    /**
     * The planner cost and I/O concurrency parameters a tablespace can override.
     */
    public const PARAMETERS = ['seq_page_cost', 'random_page_cost', 'effective_io_concurrency', 'maintenance_io_concurrency'];

    /**
     * Requires known unqualified parameters, each with an explicit non-negative number or text value.
     * @param list<Parameter> $parameters Assigned parameters in request order
     * @throws InvalidStructure
     */
    public static function parameters(array $parameters): void
    {
        Collections::objects($parameters, Parameter::class);
        foreach ($parameters as $parameter) {
            $value = $parameter->value;
            if (count($parameter->name->parts) !== 1 || !in_array($parameter->name->parts[0], self::PARAMETERS, true) || !$value instanceof Literal
                || !in_array($value->literalKind, [LiteralKind::Number, LiteralKind::Text], true) || str_starts_with($value->text, '-')) {
                throw new InvalidStructure('A tablespace parameter must be a known cost or concurrency setting with a non-negative value.');
            }
        }
    }

    /**
     * Requires one- or two-part parameter names for removal.
     * @param list<QualifiedName> $names Removed parameters in request order
     * @throws InvalidStructure
     */
    public static function names(array $names): void
    {
        Collections::objects(Collections::nonEmpty($names), QualifiedName::class);
        foreach ($names as $name) {
            if (count($name->parts) > 2) {
                throw new InvalidStructure('A tablespace parameter name has at most a namespace and a name.');
            }
        }
    }
}
