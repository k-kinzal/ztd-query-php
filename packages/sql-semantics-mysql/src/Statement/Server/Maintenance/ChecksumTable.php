<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Platform\MySql\Rules\Server\MaintainedTables;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CHECKSUM TABLE t, … [QUICK | EXTENDED]`: a request to compute a checksum of the contents of tables.
 *
 * Mirrors SQLCOM_CHECKSUM. Rule: MYSQL-CHECKSUM-TABLE-001. Each table resolves by
 * MYSQL-SERVER-TABLES-001 and its resolution is the relation fact of its
 * MaintainedTable; a table named twice is NonUniqueTable. TABLE and
 * TABLES are synonyms; TABLE is written. The server returns one row per
 * table and message; its columns are MYSQL-ADMIN-ROWS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/checksum-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('checksum table t extended');
 *     [$statement->toString(), $statement->statement->mode === \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumMode::Extended] // => ['CHECKSUM TABLE t EXTENDED', true]
 */
final class ChecksumTable implements Statement
{
    use Snapshot;

    /**
     * @var list<MaintainedTable> The tables in written order; at least one
     */
    public readonly array $tables;

    /**
     * @param list<MaintainedTable> $tables The tables in written order; at least one
     * @param ChecksumMode|null $mode QUICK or EXTENDED, when written
     */
    public function __construct(array $tables, public readonly ?ChecksumMode $mode = null)
    {
        $this->tables = (new MaintainedTables())->checked($tables);

    }

    /**
     * Records the resolution of each table, reports a table named twice, and records the result rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new MaintainedTables())->derive($derivation, $this->tables);
        (new AdminRows())->checksum($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        (new MaintainedTables())->render($out, ['CHECKSUM', 'TABLE'], false, $this->tables);
        if ($this->mode !== null) {
            $out->keyword($this->mode->value);
        }
    }
}
