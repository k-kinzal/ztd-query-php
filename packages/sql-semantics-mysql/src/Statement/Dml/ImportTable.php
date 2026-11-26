<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * IMPORT TABLE: creates MyISAM tables from the metadata files a server exported (MySQL 8.0 and later).
 *
 * Rule: MYSQL-IMPORT-001. The files are names of the file system the
 * server reads; the tables they describe are not known before the files
 * are read, so the statement provides no declaration and derives no fact.
 * Terminates: no child expression. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/import-table.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
