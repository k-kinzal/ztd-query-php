<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * A structured SQL value that writes its own fields and fixed syntax.
 *
 * @visibility public
 * @example Accepting a structured SQL value
 *     $write = static fn (\SqlSemantics\Statement\Element $value): string => \SqlSemantics\Statement\Writer::render($value);
 *     $write instanceof \Closure // => true
 */
interface Element
{
    /**
     * Writes SQL using this value's data, without a parser or a source tree.
     */
    public function write(Writer $writer): void;
}
