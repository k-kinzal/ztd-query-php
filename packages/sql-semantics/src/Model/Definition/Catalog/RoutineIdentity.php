<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;

/**
 * A function, procedure, or routine selected by name or by its overload signature.
 * @visibility public
 * @example Addressing an overloaded function
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('COMMENT ON FUNCTION app.f(integer) IS NULL');
 *     $statement->object instanceof \SqlSemantics\Model\Definition\Catalog\RoutineIdentity // => true
 *     $statement->object->target instanceof \SqlSemantics\Model\Definition\Routine\RoutineBySignature // => true
 */
final class RoutineIdentity implements ObjectAddress
{
    /**
     * Retains whether the request named an explicit signature.
     */
    public function __construct(public readonly Kind\RoutineKind $kind, public readonly RoutineByName|RoutineBySignature $target)
    {
    }
}
