<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * The LIKE clause of a SHOW: the rows whose name matches a pattern.
 *
 * The pattern is matched with the rules of the LIKE operator against the
 * column the statement names its rows by (the first column, or the
 * variable name of SHOW VARIABLES and SHOW STATUS); `%` and `_` are
 * wildcards.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/extended-show.html.
 *
 * @visibility SqlSemantics
 */
final class ShowLike implements Node
{
    use Snapshot;

    /**
     * @param Text $pattern The pattern
     */
    public function __construct(public readonly Text $pattern)
    {
    }

    /**
     * Writes LIKE and the pattern.
     */
    public function render(Output $out): void
    {
        $out->keyword('LIKE')->node($this->pattern);
    }
}
