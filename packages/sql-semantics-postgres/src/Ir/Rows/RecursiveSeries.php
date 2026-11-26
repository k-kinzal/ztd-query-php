<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A recursive single-column relation with a signed initial row and step.
 *
 * @visibility public
 * @example Building a recursive integer series
 *     $series = new \SqlSemantics\Platform\PostgreSql\Ir\Rows\RecursiveSeries(
 *         new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('0'), new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('1'),
 *         new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('3'), \SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator::Less, true,
 *         [new \SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn(0, new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('value'))],
 *     );
 *     $series->boundary->decimal // => '3'
 */
final class RecursiveSeries implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<LocalResultColumn> The ordered repeated or renamed reads
     */
    public readonly array $columns;

    /**
     * Checks the recursive operation and closes its one output position.
     *
     * @param list<LocalResultColumn> $columns The requested output fields
     */
    public function __construct(
        public readonly IntegerLiteral $start,
        public readonly IntegerLiteral $increment,
        public readonly IntegerLiteral $boundary,
        public readonly IntegerOperator $comparison,
        public readonly bool $all,
        array $columns,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        Check::input($comparison === IntegerOperator::Less || $comparison === IntegerOperator::Greater, 'A recursive series requires a strict bound.');
        Check::input($increment->decimal !== '0', 'A recursive series requires a nonzero increment.');
        Check::input(($increment->decimal[0] === '-') === ($comparison === IntegerOperator::Greater), 'A recursive series must advance toward its boundary.');
        $checked = Check::listOf($columns, LocalResultColumn::class, 'A recursive series requires a nonempty result.', 1);
        Check::input(count($checked) <= 1024, 'A recursive series can have at most 1024 result columns.');
        foreach ($checked as $column) {
            Check::input($column->slot === 0, 'A recursive result must read the single working column.');
        }
        $this->columns = $checked;
    }

    /**
     * Identifies the native language profile of the recursive expression.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the closed recursive transition and the checked consumer fields.
     */
    public function toString(): string
    {
        $renderer = new SelectSql();
        $table = '"__ztd_recursive"';
        $slot = '"__ztd_current"';
        $reference = $table . '.' . $slot;
        $start = $renderer->expression($this->start, null);
        $increment = $renderer->expression($this->increment, null);
        $boundary = $renderer->expression($this->boundary, null);
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = $reference . ' AS ' . $column->name->quoted();
        }
        $operator = 'OPERATOR(pg_catalog.+)';
        $comparison = 'OPERATOR(pg_catalog.' . $this->comparison->value . ')';

        return 'WITH RECURSIVE ' . $table . '(' . $slot . ') AS (SELECT ' . $start
            . ($this->all ? ' UNION ALL SELECT ' : ' UNION SELECT ')
            . '(' . $reference . ' ' . $operator . ' ' . $increment . ') FROM ' . $table . ' WHERE '
            . $reference . ' ' . $comparison . ' ' . $boundary . ') SELECT ' . implode(', ', $columns) . ' FROM ' . $table;
    }
}
