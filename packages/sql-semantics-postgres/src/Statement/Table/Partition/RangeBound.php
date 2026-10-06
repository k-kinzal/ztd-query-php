<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\RangeDatums;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A range partition bound: FOR VALUES FROM (lower) TO (upper).
 *
 * Mirrors `PartitionBoundSpec` with `lowerdatums` and `upperdatums`; each
 * datum is a value or MINVALUE/MAXVALUE. The values are derived where no
 * column is visible.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Writing a range bound
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES FROM (1, MINVALUE) TO (10, maxvalue)')->toString() // => 'CREATE TABLE p1 PARTITION OF p FOR VALUES FROM (1, minvalue) TO (10, maxvalue)'
 */
final class RangeBound implements PartitionBound
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar|RangeLimit> The lower datums
     */
    public readonly array $from;

    /**
     * @var non-empty-list<Scalar|RangeLimit> The upper datums
     */
    public readonly array $to;

    /**
     * @param list<Scalar|RangeLimit> $from The lower datums; at least one
     * @param list<Scalar|RangeLimit> $to The upper datums; at least one
     */
    public function __construct(array $from, array $to)
    {
        $this->from = (new RangeDatums())->checked($from);
        $this->to = (new RangeDatums())->checked($to);
    }

    /**
     * Derives the values where no column is visible.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ([...$this->from, ...$this->to] as $datum) {
            if ($datum instanceof Scalar) {
                $derivation->scalar($datum, $derivation->environment());
            }
        }
    }

    /**
     * Writes the bound.
     */
    public function render(Output $out): void
    {
        $out->keyword('FOR', 'VALUES', 'FROM')->symbol('(')->list($this->from)->symbol(')')->keyword('TO')->symbol('(')->list($this->to)->symbol(')');
    }
}
