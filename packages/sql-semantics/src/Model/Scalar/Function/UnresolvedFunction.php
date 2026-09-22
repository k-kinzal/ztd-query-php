<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

/**

 * @visibility public

  * @example Inspecting UnresolvedFunction
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT app.row_total(*)');
 *     $aggregate = $statement->outputs[0]->expression;
 *     $aggregate->function instanceof \SqlSemantics\Model\Scalar\Function\UnresolvedFunction // => true
 */
final class UnresolvedFunction implements FunctionReference
{
    /**
     * Retains the required function name when no registered overload can be selected.
     */
    public function __construct(public readonly FunctionName $function)
    {
    }

    /**
     * Returns the unresolved function identifier path.
     */
    public function name(): FunctionName
    {
        return $this->function;
    }
}
