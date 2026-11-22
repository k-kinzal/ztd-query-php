<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Index;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A request to change the statistics target of extended statistics.
 *
 * Mirrors PostgreSQL's `AlterStatsStmt` (`defnames`, `stxstattarget`, `missing_ok`). PostgreSQL 17 accepts
 * DEFAULT, which is the target -1 of earlier releases.
 * Source: https://www.postgresql.org/docs/17/sql-alterstatistics.html.
 *
 * @visibility SqlSemantics
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
