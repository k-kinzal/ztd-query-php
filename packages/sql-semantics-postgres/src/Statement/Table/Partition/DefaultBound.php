<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The DEFAULT bound: the partition holds the rows no other partition holds.
 *
 * Mirrors `PartitionBoundSpec` with `is_default`.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility SqlSemantics
 */
final class DefaultBound implements PartitionBound
{
    use Snapshot;

    /**
     * Derives nothing: the bound has no value.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes DEFAULT.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEFAULT');
    }
}
