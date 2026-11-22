<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A list partition bound: FOR VALUES IN (values).
 *
 * Mirrors `PartitionBoundSpec` with `listdatums`. The values are derived
 * where no column is visible.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility SqlSemantics
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
