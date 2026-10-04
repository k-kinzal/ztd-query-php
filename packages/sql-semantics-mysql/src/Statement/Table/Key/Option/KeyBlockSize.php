<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The KEY_BLOCK_SIZE of an index: a hint for the size of its blocks, in kilobytes.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility public
 * @example Reading an index option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT, KEY (a) KEY_BLOCK_SIZE = 8)');
 *     $create->statement->elements[1]->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\KeyBlockSize // => true
 */
final class KeyBlockSize implements IndexOption
{
    use Snapshot;

    /**
     * @param Numeral $size The block size
     */
    public function __construct(public readonly Numeral $size)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('KEY_BLOCK_SIZE')->node($this->size);
    }
}
