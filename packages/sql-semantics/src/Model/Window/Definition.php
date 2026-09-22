<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * A named window definition in the owning query scope. @visibility public

 */
final class Definition
{
    public function __construct(public readonly string $name, public readonly WindowSpecification $specification)
    {
    }
}
