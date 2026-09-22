<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

/**

 * @visibility public

 */
final class UnresolvedFunction implements FunctionReference
{
    public function __construct(public readonly FunctionName $function)
    {
    }

    public function name(): FunctionName
    {
        return $this->function;
    }
}
