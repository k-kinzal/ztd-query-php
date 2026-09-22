<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

/**

 * A named function reference, resolved against registered signatures when available. @visibility public

 */
interface FunctionReference
{
    public function name(): FunctionName;
}
