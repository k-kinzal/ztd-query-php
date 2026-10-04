<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Index;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnFacts;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A column written by name as the key of an index, a partition key or extended statistics.
 *
 * Mirrors the `name` of PostgreSQL's `IndexElem`, `PartitionElem` and
 * `StatsElem`. The name is resolved as a column of the indexed relation,
 * which is the only relation visible there.
 * Source: https://www.postgresql.org/docs/17/sql-createindex.html.
 *
 * @visibility public
 * @example Resolving an indexed column
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
 *     $table = $semantics->analyze('CREATE TABLE t (a int, b text)');
 *     $index = $semantics->analyze('CREATE INDEX i ON t (b)', $table->declarations());
 *     $index->facts->scalar($index->statement->elements[0]->key)->resolution->slot->column === $table->declarations()[0]->columns[1] // => true
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
