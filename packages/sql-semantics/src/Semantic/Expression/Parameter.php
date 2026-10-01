<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Expression;

use SqlSemantics\Semantic\Type\Undetermined;
use SqlSemantics\Semantic\Type\UnknownReason;

/**
 * A parameter whose value and type were not supplied to analysis.
 * @example Reading semantic relationships
 *     $parameter = new \SqlSemantics\Semantic\Expression\Parameter(':id');
 *     $parameter->toString() // => ':id'
 *
 * @visibility public
 */
final class Parameter
{
    /**
     * The result type or the explicit reason no type can be established.
     */
    public readonly Undetermined $type;

    /**
     * Parameters can be NULL until their values are supplied.
     */
    public readonly \SqlSemantics\Core\Type\Nullability $nullability;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly string $name)
    {
        assert(preg_match('/^(?:\?[0-9]*|\$[1-9][0-9]*|[:@$][a-zA-Z_][a-zA-Z_0-9]*)$/D', $name) === 1, 'Invalid parameter name.');
        $this->type = new Undetermined(UnknownReason::ParameterNotSupplied);
        $this->nullability = \SqlSemantics\Core\Type\Nullability::Unknown;
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return $this->name;
    }
}
