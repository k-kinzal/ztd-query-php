<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine;

use SqlSemantics\Type\TypeDescriptor;

/**
 * An argument's declared type, direction, optional name, and set-valued type request.
 * @visibility public
 * @example Reading an argument type
 *     $type = \SqlSemantics\Type\TypeDescriptor::builtin(\SqlSemantics\Dialect::PostgreSql, 'integer');
 *     $argument = new \SqlSemantics\Model\Definition\Routine\RoutineParameter($type);
 *     $argument->mode === \SqlSemantics\Model\Definition\Routine\ParameterMode::Implicit // => true
 */
final class RoutineParameter
{
    /**
     * Validates the type at construction, before any statement accepts the argument.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly TypeDescriptor|ColumnTypeReference $type,
        public readonly ParameterMode $mode = ParameterMode::Implicit,
        public readonly ?string $name = null,
        public readonly bool $setOf = false,
    ) {
        ArgumentTypeInvariant::validate($type);
    }
}
