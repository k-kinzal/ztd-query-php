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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

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
 *     [$load->duplicates, count($load->columns), $load->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling::Replace, 2, "LOAD DATA INFILE 'f' REPLACE INTO TABLE t COLUMNS TERMINATED BY ',' IGNORE 1 LINES (a, @b) SET c = @b * 2"]
 * @example Retaining an incompatible bulk option combination for execution
 *     $load = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("LOAD DATA S3 'f' INTO TABLE t (a) ALGORITHM=BULK");
 *     $load->diagnostics()[0]->message() // => 'Incorrect usage of LOAD DATA with BULK Algorithm and column list specification'
 * @example Refusing a legacy dotted column combined with an S3 source
 *     $column = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written);
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable(\SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat::Xml, null, new \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput(true, \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::S3, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('1')), null, new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))), columns: [$column]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class LoadTable extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<FieldOption> The FIELDS options in written order
     */
    public readonly array $fields;

    /**
     * @var list<LineOption> The LINES options in written order
     */
    public readonly array $lines;

    /**
     * @var list<ColumnUse|UserVariable>|null Input destinations in written order; null omits the list and [] writes (), both selecting every column
     */
    public readonly ?array $columns;

    /**
     * @var list<Assignment|DeferredColumnAssignment> The SET assignments in written order
     */
    public readonly array $assignments;

    /**
     * @param LoadFormat $format DATA or XML
     * @param LoadLock|null $lock The scheduling modifier
     * @param LoadInput $input What is read
     * @param DuplicateHandling|null $duplicates What a row that duplicates a unique key does
     * @param WriteTarget $table The table the rows are written to; it has no correlation name
     * @param \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm $charsetForm The written character-set introducer
     * @param CharsetName|null $charset The character set of the file, including explicit DEFAULT in MySQL 5.6 and 5.7
     * @param Text|null $compression The compression algorithm of the files (9.x bulk load)
     * @param Text|null $rowTag The XML element of a row, `ROWS IDENTIFIED BY`
     * @param list<FieldOption> $fields The FIELDS options
     * @param list<LineOption> $lines The LINES options
     * @param Numeral|null $ignored The number of lines or rows skipped at the start
     * @param list<ColumnUse|UserVariable>|null $columns Input destinations; null omits the list and [] retains an explicit empty list
     * @param list<Assignment|DeferredColumnAssignment> $assignments The SET assignments
     * @param BulkOptions|null $bulk The options of a bulk load
     * @throws InvalidConstruction When the table has a correlation name or a member of a list is of the wrong class
     * @param bool $ignoreRows Whether ignored input lines are written as ROWS
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
        ?array $columns = null,
        array $assignments = [],
        public readonly ?BulkOptions $bulk = null,
        ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        public readonly bool $ignoreRows = false,
        public readonly \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm $charsetForm = \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Named
    ) {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input($charsetForm->named(), 'A file character set uses a named charset form.');
        Check::input($table->alias === null, 'The table of LOAD has no correlation name.');
        Check::input($compression === null || $compression->radix === null, 'A LOAD compression algorithm is quoted text.');
        Check::input($ignored === null || $ignored->isIntegerToken(), 'A LOAD ignored-row count is a decimal integer token no greater than 2147483647.');
        $this->fields = Check::listOf($fields, FieldOption::class, 'The FIELDS clause holds field options.');
        $this->lines = Check::listOf($lines, LineOption::class, 'The LINES clause holds line options.');
        $list = [];
        foreach (Check::listOf($columns ?? [], Node::class, 'The column list of LOAD is an ordered list.') as $column) {
            Check::input($column instanceof ColumnUse || $column instanceof UserVariable, 'LOAD reads fields into columns and user variables.');
            $list[] = $column;
        }
        $this->columns = $columns === null ? null : $list;
        $items = Check::listOf($assignments, Node::class, 'The SET clause of LOAD holds assignments.');
        foreach ($items as $item) {
            Check::input($item instanceof Assignment || $item instanceof DeferredColumnAssignment, 'LOAD assignments retain scalar, default and non-scalar roles.');
        }
        $this->assignments = $items;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Answers whether assignment or fixed-width input preparation must reject this request.
     */
    public function hasExecutionCondition(): bool
    {
        return array_filter($this->assignments, static fn ($assignment): bool => $assignment instanceof DeferredColumnAssignment) !== []
            || (new \SqlSemantics\Platform\MySql\Rules\Dml\LoadOptions())->fixedVariables($this->format, $this->fields, $this->columns ?? []);
    }

    /**
     * Derives the table, the columns and the assignments; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        Check::input($this->charset === null || $this->charset->name !== null || in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true), 'LOAD CHARACTER SET DEFAULT belongs to MySQL 5.6 and 5.7.');
        (new LoadFacts())->derive($this, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        (new \SqlSemantics\Platform\MySql\Rendering\LoadRendering())->render($this, $out);
    }
}
