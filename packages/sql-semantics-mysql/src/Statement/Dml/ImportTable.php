<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

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
 *     [count($import->files), $import->toString()] // => [2, "IMPORT TABLE FROM '/tmp/t1_*.sdi', '/tmp/t2.sdi'"]
 */
final class ImportTable extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<Text> The file name patterns in written order
     */
    public readonly array $files;

    /**
     * @param list<Text> $files The file name patterns; at least one
     */
    public function __construct(array $files, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->files = Check::listOf($files, Text::class, 'IMPORT TABLE names at least one file.', 1);
        foreach ($this->files as $file) {
            Check::input($file->radix === null, 'An imported file name is a quoted string.');
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
