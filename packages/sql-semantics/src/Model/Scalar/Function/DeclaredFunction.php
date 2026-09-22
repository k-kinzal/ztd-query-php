<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

/**

 * @visibility public

 */
final class DeclaredFunction implements FunctionReference
{
    public function __construct(public readonly \SqlSemantics\Schema\FunctionSignature $signature)
    {
    }

    public function name(): FunctionName
    {
        return new FunctionName([...($this->signature->schema === null ? [] : [$this->signature->schema]), $this->signature->name]);
    }
}
