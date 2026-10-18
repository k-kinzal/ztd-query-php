<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Declaration;

use PhpParser\Node;

/**
 * Captured parser nodes for a callable compiled only when demanded.
 * @visibility Deriver\Deriver\Lowerer\Declaration
 * @visibility Deriver\Deriver\Lowerer
 * @visibility Deriver\Deriver\Sources
 */
final class CallableSource
{
    /**
     * @param string $symbol Qualified identity
     * @param Node $node Callable declaration or synthetic script
     * @param string $path Captured source path
     * @param string $className Lexical class
     * @param bool $strict Calling file's scalar coercion mode
     */
    public function __construct(public readonly string $symbol, public readonly Node $node, public readonly string $path, public readonly string $className = '', public readonly bool $strict = false)
    {
    }
}
