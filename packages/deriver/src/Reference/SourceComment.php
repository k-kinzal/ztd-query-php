<?php

declare(strict_types=1);

namespace Deriver\Reference;

/**
 * A raw PHPDoc comment attached to a source statement or expression, without inferred types.
 * @visibility public
 * @example Keeping raw source documentation
 *     $source = new \Deriver\Reference\SourceRef('snapshot', 'source.php', 0, 10);
 *     (new \Deriver\Reference\SourceComment($source, 'raw annotation'))->text // => 'raw annotation'
 */
final class SourceComment
{
    /**
     * @param SourceRef $source Commented source node
     * @param string $text Uninterpreted PHPDoc text
     */
    public function __construct(public readonly SourceRef $source, public readonly string $text)
    {
    }
}
