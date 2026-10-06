<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The LIKE clause of a SHOW: the rows whose name matches a pattern.
 *
 * The pattern is matched with the rules of the LIKE operator against the
 * column the statement names its rows by (the first column, or the
 * variable name of SHOW VARIABLES and SHOW STATUS); `%` and `_` are
 * wildcards.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/extended-show.html.
 *
 * @visibility public
 * @example Reading the pattern
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW DATABASES LIKE 'shop%'");
 *     $show->statement->filter->pattern->value // => 'shop%'
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
