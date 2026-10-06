<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Dml\FileFormat;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The text file format of SELECT ... INTO OUTFILE: character set, FIELDS options and LINES options.
 *
 * Mirrors the server's `sql_exchange` with its field and line separators.
 * The options are kept in written order; an option written twice is kept
 * twice, and the server uses the last one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select-into.html,
 * https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility public
 * @example Reading the format of INTO OUTFILE
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT 1 INTO OUTFILE 'f' CHARACTER SET utf8mb4 FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY ';'");
 *     [$query->statement->into->format->charset->name->value, count($query->statement->into->format->fields), count($query->statement->into->format->lines)] // => ['utf8mb4', 2, 1]
 */
final class TextFileFormat implements FileFormat
{
    use Snapshot;

    /**
     * @var list<FieldOption> The FIELDS options in written order; empty without FIELDS
     */
    public readonly array $fields;

    /**
     * @var list<LineOption> The LINES options in written order; empty without LINES
     */
    public readonly array $lines;

    /**
     * @param CharsetName|null $charset The character set of the file
     * @param list<FieldOption> $fields The FIELDS options
     * @param list<LineOption> $lines The LINES options
     */
    public function __construct(public readonly ?CharsetName $charset = null, array $fields = [], array $lines = [])
    {
        $this->fields = Check::listOf($fields, FieldOption::class, 'The FIELDS clause holds field options.');
        $this->lines = Check::listOf($lines, LineOption::class, 'The LINES clause holds line options.');
    }

    /**
     * Writes the character set, the FIELDS options and the LINES options.
     */
    public function render(Output $out): void
    {
        if ($this->charset !== null) {
            $out->keyword('CHARSET')->node($this->charset);
        }
        if ($this->fields !== []) {
            $out->keyword('COLUMNS');
            foreach ($this->fields as $option) {
                $out->node($option);
            }
        }
        if ($this->lines !== []) {
            $out->keyword('LINES');
            foreach ($this->lines as $option) {
                $out->node($option);
            }
        }
    }
}
