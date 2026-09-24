<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The declared properties of a new stored routine; omitted properties take the server defaults.
 * @visibility public
 * @example Reading declared and default characteristics
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() DETERMINISTIC BEGIN END');
 *     $statement->characteristics->deterministic // => true
 *     $statement->characteristics->dataAccess === \SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess::Contains // => true
 */
final class RoutineCharacteristics
{
    /**
     * Keeps the last declaration of each property; a comment is a MySQL text literal.
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly bool $deterministic = false,
        public readonly SqlDataAccess $dataAccess = SqlDataAccess::Contains,
        public readonly RoutineSecurity $security = RoutineSecurity::Definer,
        public readonly ?Literal $comment = null,
    ) {
        if ($comment !== null && ($comment->type->dialect !== Dialect::MySql || $comment->literalKind !== LiteralKind::Text)) {
            throw new InvalidStructure('A routine comment requires a MySQL text literal.');
        }
    }
}
