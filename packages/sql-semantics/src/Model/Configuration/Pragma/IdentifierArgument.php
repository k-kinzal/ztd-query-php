<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Pragma;

/**
 * A classified pragma argument.
 *
 * @visibility public
 */
final class IdentifierArgument implements Argument
{
    /**

     */
    public function __construct(public readonly string $name)
    {
    }
}
