<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * MINVALUE or MAXVALUE in a range partition bound: below or above every value.
 *
 * The grammar reads the word as a column reference; the server takes a
 * one-part column reference named `minvalue` or `maxvalue` in a range bound
 * as the infinite bound (`PARTITION_RANGE_DATUM_MINVALUE`/`MAXVALUE`).
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading an infinite bound
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES FROM (MINVALUE) TO (10)');
 *     $create->statement->definition->bound->from[0]->maximum() // => false
 */
final class RangeLimit implements Node
{
    use Snapshot;

    /**
     * @param Name $word The word, `minvalue` or `maxvalue`
     */
    public function __construct(public readonly Name $word)
    {
        Check::input($word->value === 'minvalue' || $word->value === 'maxvalue', 'An infinite range bound is minvalue or maxvalue.');
    }

    /**
     * Tells whether the limit is MAXVALUE.
     */
    public function maximum(): bool
    {
        return $this->word->value === 'maxvalue';
    }

    /**
     * Writes the word.
     */
    public function render(Output $out): void
    {
        $out->name($this->word);
    }
}
