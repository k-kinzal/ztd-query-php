<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\RangeDatums;

/**
 * A range partition bound: FOR VALUES FROM (lower) TO (upper).
 *
 * Mirrors `PartitionBoundSpec` with `lowerdatums` and `upperdatums`; each
 * datum is a value or MINVALUE/MAXVALUE. The values are derived where no
 * column is visible.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility SqlSemantics
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
