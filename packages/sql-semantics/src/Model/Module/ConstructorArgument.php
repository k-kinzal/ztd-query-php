<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Module;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One text argument passed to SQLite's virtual-table module constructor.
 * SQLite assigns interpretation of this string to the named module.
 * @see https://www.sqlite.org/vtab.html#the_xcreate_method
 * @visibility public
 * @example Retaining one module argument
 *     $argument = new \SqlSemantics\Model\Module\ConstructorArgument('tokenize = "porter ascii"');
 *     $argument->text // => 'tokenize = "porter ascii"'
 *     new \SqlSemantics\Model\Module\ConstructorArgument('title, body') // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ConstructorArgument
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $text)
    {
        \SqlSemantics\Model\Validation\ModuleArgument::check($text);
    }
}
