<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Dml\LoadFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * LOAD DATA and LOAD XML: reads rows from a file into a table.
 *
 * Mirrors the server's `PT_load_table` with its `sql_exchange`. The facts
 * follow MYSQL-LOAD-001. A clause written twice in the field or line
 * options is kept twice.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html, https://dev.mysql.com/doc/refman/8.4/en/load-xml.html.
 *
 * @visibility public
 * @example Reading a LOAD DATA
 *     $load = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("LOAD DATA INFILE 'f' REPLACE INTO TABLE t FIELDS TERMINATED BY ',' IGNORE 1 LINES (a, @b) SET c = @b * 2");
 *     [$load->statement->duplicates, count($load->statement->columns), $load->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling::Replace, 2, "LOAD DATA INFILE 'f' REPLACE INTO TABLE t COLUMNS TERMINATED BY ',' IGNORE 1 LINES (a, @b) SET c = @b * 2"]
 */
final class LoadTable implements Statement
{
    use Snapshot;

    /**
     * @var list<FieldOption> The FIELDS options in written order
     */
    public readonly array $fields;

    /**
     * @var list<LineOption> The LINES options in written order
     */
    public readonly array $lines;

    /**
     * @var list<ColumnUse|UserVariable> The columns and user variables the fields are read into, in written order; empty for every column
     */
    public readonly array $columns;

    /**
     * @var list<Assignment> The SET assignments in written order
     */
    public readonly array $assignments;

    /**
     * @param LoadFormat $format DATA or XML
     * @param LoadLock|null $lock The scheduling modifier
     * @param LoadInput $input What is read
     * @param DuplicateHandling|null $duplicates What a row that duplicates a unique key does
     * @param WriteTarget $table The table the rows are written to; it has no correlation name
     * @param CharsetName|null $charset The character set of the file
     * @param Text|null $compression The compression algorithm of the files (9.x bulk load)
     * @param Text|null $rowTag The XML element of a row, `ROWS IDENTIFIED BY`
     * @param list<FieldOption> $fields The FIELDS options
     * @param list<LineOption> $lines The LINES options
     * @param Numeral|null $ignored The number of lines or rows skipped at the start
     * @param list<ColumnUse|UserVariable> $columns The columns and user variables the fields are read into
     * @param list<Assignment> $assignments The SET assignments
     * @param BulkOptions|null $bulk The options of a bulk load
     * @throws InvalidConstruction When the table has a correlation name or a member of a list is of the wrong class
     */
    public function __construct(
        public readonly LoadFormat $format,
        public readonly ?LoadLock $lock,
        public readonly LoadInput $input,
        public readonly ?DuplicateHandling $duplicates,
        public readonly WriteTarget $table,
        public readonly ?CharsetName $charset = null,
        public readonly ?Text $compression = null,
        public readonly ?Text $rowTag = null,
        array $fields = [],
        array $lines = [],
        public readonly ?Numeral $ignored = null,
        array $columns = [],
        array $assignments = [],
        public readonly ?BulkOptions $bulk = null,
    ) {
        Check::input($table->alias === null, 'The table of LOAD has no correlation name.');
        $this->fields = Check::listOf($fields, FieldOption::class, 'The FIELDS clause holds field options.');
        $this->lines = Check::listOf($lines, LineOption::class, 'The LINES clause holds line options.');
        $list = [];
        foreach (Check::listOf($columns, Node::class, 'The column list of LOAD is an ordered list.') as $column) {
            Check::input($column instanceof ColumnUse || $column instanceof UserVariable, 'LOAD reads fields into columns and user variables.');
            $list[] = $column;
        }
        $this->columns = $list;
        $this->assignments = Check::listOf($assignments, Assignment::class, 'The SET clause of LOAD holds assignments.');
    }

    /**
     * Derives the table, the columns and the assignments; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new LoadFacts())->derive($this, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('LOAD', $this->format->value);
        if ($this->lock !== null) {
            $out->keyword($this->lock->value);
        }
        $out->node($this->input);
        if ($this->duplicates !== null) {
            $out->keyword($this->duplicates->value);
        }
        $out->keyword('INTO', 'TABLE')->node($this->table);
        if ($this->charset !== null) {
            $out->keyword('CHARSET')->node($this->charset);
        }
        if ($this->compression !== null) {
            $out->keyword('COMPRESSION')->symbol('=')->node($this->compression);
        }
        if ($this->rowTag !== null) {
            $out->keyword('ROWS', 'IDENTIFIED', 'BY')->node($this->rowTag);
        }
        if ($this->fields !== []) {
            $out->keyword('COLUMNS');
        }
        foreach ($this->fields as $option) {
            $out->node($option);
        }
        if ($this->lines !== []) {
            $out->keyword('LINES');
        }
        foreach ($this->lines as $option) {
            $out->node($option);
        }
        if ($this->ignored !== null) {
            $out->keyword('IGNORE')->node($this->ignored)->keyword('LINES');
        }
        if ($this->columns !== []) {
            $out->symbol('(')->list($this->columns)->symbol(')');
        }
        if ($this->assignments !== []) {
            $out->keyword('SET')->list($this->assignments);
        }
        $out->node($this->bulk);
    }
}
