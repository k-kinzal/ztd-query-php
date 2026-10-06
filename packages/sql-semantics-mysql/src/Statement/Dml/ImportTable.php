<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * IMPORT TABLE: creates MyISAM tables from the metadata files a server exported (MySQL 8.0 and later).
 *
 * Rule: MYSQL-IMPORT-001. The files are names of the file system the
 * server reads; the tables they describe are not known before the files
 * are read, so the statement provides no declaration and derives no fact.
 * Terminates: no child expression. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/import-table.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the files of IMPORT TABLE
 *     $import = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("IMPORT TABLE FROM '/tmp/t1_*.sdi', '/tmp/t2.sdi'");
 *     [count($import->statement->files), $import->toString()] // => [2, "IMPORT TABLE FROM '/tmp/t1_*.sdi', '/tmp/t2.sdi'"]
 */
final class ImportTable implements Statement
{
    use Snapshot;

    /**
     * @var list<Text> The file name patterns in written order
     */
    public readonly array $files;

    /**
     * @param list<Text> $files The file name patterns; at least one
     */
    public function __construct(array $files)
    {
        $this->files = Check::listOf($files, Text::class, 'IMPORT TABLE names at least one file.', 1);
    }

    /**
     * Derives nothing: the statement holds no expression and returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('IMPORT', 'TABLE', 'FROM')->list($this->files);
    }
}
