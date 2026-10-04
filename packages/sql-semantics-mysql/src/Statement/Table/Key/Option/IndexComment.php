<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The COMMENT of an index.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility public
 * @example Reading an index option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT, KEY (a) COMMENT \'x\')');
 *     $create->statement->elements[1]->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexComment // => true
 */
final class IndexComment implements IndexOption
{
    use Snapshot;

    /**
     * @param Text $comment The comment
     */
    public function __construct(public readonly Text $comment)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('COMMENT')->node($this->comment);
    }
}
