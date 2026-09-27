<?php

declare(strict_types=1);

namespace Deriver\Reference;

/**
 * A stable SymbolRef within a project snapshot.
 *
 * @visibility public
 * @example Creating a reference
 *     (new \Deriver\Reference\SymbolRef('App\\run'))->name // => 'App\\run'
 */
final class SymbolRef
{
    /**
     * @param string $name Fully qualified callable name
     */
    public function __construct(
        public readonly string $name,
    ) {
    }
}
