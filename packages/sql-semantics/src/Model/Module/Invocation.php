<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Module;

use SqlSemantics\Model\Validation\Collections;

/**
 * A virtual-table constructor lookup and its ordered module arguments.
 * @visibility public
 */
final class Invocation
{
    /**
     * @param list<ConstructorArgument> $arguments
     */
    public function __construct(public readonly string $module, public readonly array $arguments = [])
    {
        Collections::objects($arguments, ConstructorArgument::class);
    }
}
