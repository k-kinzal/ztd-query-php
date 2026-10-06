<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Index;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to change the statistics target of extended statistics.
 *
 * Mirrors PostgreSQL's `AlterStatsStmt` (`defnames`, `stxstattarget`, `missing_ok`). PostgreSQL 17 accepts
 * DEFAULT, which is the target -1 of earlier releases.
 * Source: https://www.postgresql.org/docs/17/sql-alterstatistics.html.
 *
 * @visibility public
 * @example Changing a statistics target
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER STATISTICS IF EXISTS s SET STATISTICS 100');
 *     $statement->toString() // => 'ALTER STATISTICS IF EXISTS s SET STATISTICS 100'
 */
final class AlterStatistics implements Statement
{
    use Snapshot;

    /**
     * @param DottedName $name The statistics object
     * @param SignedNumber|null $target The target; null for DEFAULT
     * @param bool $ifExists Whether IF EXISTS is written
     */
    public function __construct(
        public readonly DottedName $name,
        public readonly ?SignedNumber $target,
        public readonly bool $ifExists = false,
    ) {
    }

    /**
     * Derives nothing: the statement names a catalog object.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'STATISTICS');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->node($this->name)->keyword('SET', 'STATISTICS');
        if ($this->target === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->node($this->target);
        }
    }
}
