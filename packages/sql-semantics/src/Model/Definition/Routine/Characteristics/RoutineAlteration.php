<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Characteristics;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests changes to routine metadata; omitted properties remain unchanged.
 * @visibility public
 * @example Keeping properties outside the request unchanged
 *     $changes = new \SqlSemantics\Model\Definition\Routine\Characteristics\RoutineAlteration(security: \SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity::Invoker);
 *     $changes->dataAccess // => null
 */
final class RoutineAlteration
{
    /**
     * Retains a declared language name, data access, security context, and comment independently.
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly ?string $language = null,
        public readonly ?SqlDataAccess $dataAccess = null,
        public readonly ?RoutineSecurity $security = null,
        public readonly ?Literal $comment = null,
    ) {
        if ($language === '') {
            throw new InvalidStructure('A routine language requires a nonempty name.');
        }
        if ($comment !== null && ($comment->type->dialect !== Dialect::MySql || $comment->literalKind !== LiteralKind::Text)) {
            throw new InvalidStructure('A routine comment requires a MySQL text literal.');
        }
    }
}
