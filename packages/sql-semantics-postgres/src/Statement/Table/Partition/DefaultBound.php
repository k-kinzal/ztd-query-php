<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The DEFAULT bound: the partition holds the rows no other partition holds.
 *
 * Mirrors `PartitionBoundSpec` with `is_default`.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Writing a default partition
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('create table p0 partition of p default')->toString() // => 'CREATE TABLE p0 PARTITION OF p DEFAULT'
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
