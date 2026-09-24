<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Option;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Names the planner support function of a function.
 * @visibility public
 * @example Reading the support function
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() SUPPORT app.f_support');
 *     $statement->changes[0]->function->parts // => ['app', 'f_support']
 */
final class SupportFunction implements RoutineOption
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $function)
    {
        CatalogInvariant::name($function, 3);
    }
}
