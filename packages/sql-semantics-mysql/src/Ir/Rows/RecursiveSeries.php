<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\SelectSql;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A recursive single-column relation with a signed initial row and step.
 *
 * @visibility public
 * @example Building a recursive integer series
 *     $series = new \SqlSemantics\Platform\MySql\Ir\Rows\RecursiveSeries(
 *         new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('0'), new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('1'),
 *         new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('3'), \SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator::Less, true,
 *         [new \SqlSemantics\Platform\MySql\Ir\Rows\LocalResultColumn(0, new \SqlSemantics\Platform\MySql\Ir\Identifier('value'))],
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
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
    ) {
        $available = match ($release) {
            GrammarRelease::MySql5651, GrammarRelease::MySql5744 => false,
            GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830,
            GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910 => true,
        };
        Check::input($available, 'Recursive WITH requires MySQL 8.0 or later.');
        Check::input($comparison === IntegerOperator::Less || $comparison === IntegerOperator::Greater, 'A recursive series requires a strict bound.');
        Check::input($increment->decimal !== '0', 'A recursive series requires a nonzero increment.');
        Check::input(($increment->decimal[0] === '-') === ($comparison === IntegerOperator::Greater), 'A recursive series must advance toward its boundary.');
        $checked = Check::listOf($columns, LocalResultColumn::class, 'A recursive series requires a nonempty result.', 1);
        Check::input(count($checked) <= 1024, 'A recursive series can have at most 1024 result columns.');
        foreach ($checked as $column) {
            Check::input($column->slot === 0, 'A recursive result must read the single working column.');
            Check::input($column->name->value === ltrim($column->name->value, " \t\r\n\v\f"), 'A MySQL result name cannot start with SQL whitespace.');
        }
        $this->columns = $checked;
    }

    /**
     * Identifies the native language profile of the recursive expression.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native';
    }

    /**
     * Emits the closed recursive transition and the checked consumer fields.
     */
    public function toString(): string
    {
        $renderer = new SelectSql();
        $table = '`__ztd_recursive`';
        $slot = '`__ztd_current`';
        $reference = $table . '.' . $slot;
        $start = $renderer->expression($this->start, null);
        $increment = $renderer->expression($this->increment, null);
        $boundary = $renderer->expression($this->boundary, null);
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = $reference . ' AS ' . $column->name->quoted();
        }
        $operator = '+';
        $comparison = $this->comparison->value;

        return 'WITH RECURSIVE ' . $table . '(' . $slot . ') AS (SELECT ' . $start
            . ($this->all ? ' UNION ALL SELECT ' : ' UNION SELECT ')
            . '(' . $reference . ' ' . $operator . ' ' . $increment . ') FROM ' . $table . ' WHERE '
            . $reference . ' ' . $comparison . ' ' . $boundary . ') SELECT ' . implode(', ', $columns) . ' FROM ' . $table;
    }
}
