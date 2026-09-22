<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

/**

 * @visibility public

  * @example Inspecting DeclaredFunction
 *     $integer = new \SqlSemantics\Type\TypeDescriptor(\SqlSemantics\Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::Integer);
 *     $signature = new \SqlSemantics\Schema\FunctionSignature('row_total', [], $integer, \SqlSemantics\Type\Nullability::NotNull, aggregate: true, schema: 'app');
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()->withFunctions($signature);
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT app.row_total(*) FILTER (WHERE TRUE)');
 *     $aggregate = $statement->outputs[0]->expression;
 *     $aggregate->function instanceof \SqlSemantics\Model\Scalar\Function\DeclaredFunction // => true
 */
final class DeclaredFunction implements FunctionReference
{
    /**
     * Retains the exact overload selected from the schema registrations.
     */
    public function __construct(public readonly \SqlSemantics\Schema\FunctionSignature $signature)
    {
    }

    /**
     * Returns the registered function name with its optional namespace.
     */
    public function name(): FunctionName
    {
        return new FunctionName([...($this->signature->schema === null ? [] : [$this->signature->schema]), $this->signature->name]);
    }
}
