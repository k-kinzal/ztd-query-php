<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Index;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnFacts;

/**
 * A column written by name as the key of an index, a partition key or extended statistics.
 *
 * Mirrors the `name` of PostgreSQL's `IndexElem`, `PartitionElem` and
 * `StatsElem`. The name is resolved as a column of the indexed relation,
 * which is the only relation visible there.
 * Source: https://www.postgresql.org/docs/17/sql-createindex.html.
 *
 * @visibility SqlSemantics
 */
final class ColumnKey implements Scalar
{
    use Snapshot;

    /**
     * @param Name $column The column name
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Resolves the name as a column.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new ColumnFacts())->derive($derivation, $environment, [$this->column]);
    }

    /**
     * Writes the name.
     */
    public function render(Output $out): void
    {
        $out->name($this->column);
    }
}
