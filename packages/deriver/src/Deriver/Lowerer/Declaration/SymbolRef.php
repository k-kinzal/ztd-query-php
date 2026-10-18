<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Declaration;

/**
 * A stable SymbolRef within a analysis snapshot.
 *
 * @visibility root
 * @example Creating a reference
 *     (new \Deriver\Deriver\Lowerer\Declaration\SymbolRef('App\\run'))->name // => 'App\\run'
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
