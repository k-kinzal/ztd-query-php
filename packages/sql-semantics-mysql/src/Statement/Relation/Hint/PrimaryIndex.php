<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation\Hint;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * The primary key index named by the PRIMARY keyword in an index list.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/index-hints.html.
 *
 * @visibility public
 * @example Reading the primary key in an index hint
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t USE INDEX (PRIMARY, i)');
 *     $query->query->from->indexHints[0]->indexes[0] instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex // => true
 */
final class PrimaryIndex implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('PRIMARY');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }
}
