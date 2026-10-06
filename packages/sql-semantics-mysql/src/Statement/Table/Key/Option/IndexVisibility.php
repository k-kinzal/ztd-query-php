<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Option;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * VISIBLE or INVISIBLE: whether the optimizer uses the index (MySQL 8.0 and later).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility public
 * @example Reading an index option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT, KEY (a) INVISIBLE)');
 *     $create->statement->elements[1]->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility // => true
 */
final class IndexVisibility implements IndexOption
{
    use Snapshot;

    /**
     * @param bool $visible Whether VISIBLE is written
     */
    public function __construct(public readonly bool $visible)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->visible ? 'VISIBLE' : 'INVISIBLE');
    }
}
