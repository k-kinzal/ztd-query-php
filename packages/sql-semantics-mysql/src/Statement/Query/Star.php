<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Rendering\Output;

/**
 * The unqualified `*` of a select list: every column of every table of the FROM clause.
 *
 * MySQL accepts it only as the first item of the list. The selection
 * expands it (MYSQL-STAR-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility public
 * @example Reading a star item
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT * FROM t');
 *     $query->query->items[0] instanceof \SqlSemantics\Platform\MySql\Statement\Query\Star // => true
 */
final class Star implements SelectItem
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Writes the star.
     */
    public function render(Output $out): void
    {
        $out->symbol('*');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }
}
