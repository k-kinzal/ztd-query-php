<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

/**
 * One named SQL/JSON path variable supplied by a PASSING clause.
 * @visibility public
 */
final class PassingArgument
{
    /**
     * Associates the expression with a path-variable name without resolving its value.
     */
    public function __construct(public readonly string $name, public readonly Input $input)
    {
    }
}
