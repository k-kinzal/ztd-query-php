<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Module;

use SqlSemantics\Model\Validation\Collections;

/**
 * A virtual-table constructor lookup and its ordered module arguments.
 * @visibility public
 * @example Describing a module constructor call
 *     $invocation = new \SqlSemantics\Model\Module\Invocation('fts5', [new \SqlSemantics\Model\Module\ConstructorArgument('title')]);
 *     $invocation->module // => 'fts5'
 *     $invocation->arguments[0]->text // => 'title'
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
