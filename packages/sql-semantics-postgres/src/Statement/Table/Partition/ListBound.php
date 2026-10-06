<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A list partition bound: FOR VALUES IN (values).
 *
 * Mirrors `PartitionBoundSpec` with `listdatums`. The values are derived
 * where no column is visible.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading the values of a list bound
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE TABLE p1 PARTITION OF p FOR VALUES IN ('a', 'b')");
 *     count($create->statement->definition->bound->values) // => 2
 */
final class ListBound implements PartitionBound
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The values
     */
    public readonly array $values;

    /**
     * @param list<Scalar> $values The values; at least one
     */
    public function __construct(array $values)
    {
        $this->values = Check::listOf($values, Scalar::class, 'A list bound has at least one value.', 1);
    }

    /**
     * Derives the values where no column is visible.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->values as $value) {
            $derivation->scalar($value, $derivation->environment());
        }
    }

    /**
     * Writes the bound.
     */
    public function render(Output $out): void
    {
        $out->keyword('FOR', 'VALUES', 'IN')->symbol('(')->list($this->values)->symbol(')');
    }
}
