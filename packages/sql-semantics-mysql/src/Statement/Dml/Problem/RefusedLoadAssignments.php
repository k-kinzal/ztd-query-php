<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use LogicException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\{BulkOptions, FieldOption, LineOption, LoadFormat, LoadInput, LoadLock};
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Node;

/**
 * A complete LOAD request with an intrinsic input or SET assignment constraint.
 *
 * This diagnostic retains input and assignment roles without publishing an
 * ordinary statement. Database and file-entry conditions can precede the
 * intrinsic assignment constraint. It retains no SQL text or parser tree.
 *
 * @visibility public
 * @example Retaining a LOAD assignment outside the ordinary statement domain
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("LOAD DATA INFILE 'f' INTO TABLE t SET a=ROW(1,2)") // throws \SqlSemantics\Construction\RejectedInput
 */
final class RefusedLoadAssignments implements Diagnostic, Node
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
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
    ) {
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
        $items = Check::listOf($assignments, Node::class, 'A retained LOAD holds ordered assignments.');
        $deferred = false;
        foreach ($items as $item) {
            Check::input($item instanceof Assignment || $item instanceof DeferredColumnAssignment, 'LOAD assignments retain scalar, default and non-scalar roles.');
            $deferred = $deferred || $item instanceof DeferredColumnAssignment;
        }
        Check::input($deferred || (new \SqlSemantics\Platform\MySql\Rules\Dml\LoadOptions())->fixedVariables($format, $this->fields, $this->columns ?? []), 'A retained LOAD requires a non-scalar SET assignment or a fixed-row input variable.');
        $this->assignments = $items;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Describes the intrinsic assignment condition independently of session state.
     *
     * @throws LogicException If the constructor invariant is broken
     */
    public function message(): string
    {
        foreach ($this->assignments as $assignment) {
            if ($assignment instanceof DeferredColumnAssignment) {
                return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$assignment->value])->message();
            }
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\Dml\LoadOptions())->fixedVariables($this->format, $this->fields, $this->columns ?? [])) {
            return 'Fixed-row LOAD DATA requires column destinations with defined widths.';
        }
        throw new LogicException('A retained LOAD has an intrinsic assignment condition.');
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        (new \SqlSemantics\Platform\MySql\Rendering\LoadRendering())->render($this, $out);
    }
}
