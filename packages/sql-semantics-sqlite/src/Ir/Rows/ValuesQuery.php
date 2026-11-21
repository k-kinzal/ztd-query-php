<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete SQLite VALUES statement with one fixed result width.
 *
 * @visibility public
 * @example Constructing a statement of explicit rows
 *     $row = new \SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow([new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('1')]);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery([$row, $row]))->toString() // => 'VALUES (1), (1)'
 */
final class ValuesQuery implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<ValueRow>
     */
    public readonly array $rows;

    /**
     * Checks that all rows have one width and fit the full statement budget.
     *
     * @param list<ValueRow> $rows
     */
    public function __construct(array $rows)
    {
        $checked = Check::listOf($rows, ValueRow::class, 'A VALUES statement needs a nonempty list of rows.', 1);
        $width = count($checked[0]->values);
        Check::input(count($checked) * $width <= 8192, 'The expanded VALUES size exceeds the IR profile.');
        $pending = [];
        foreach ($checked as $row) {
            Check::input(count($row->values) === $width, 'All VALUES rows must have the same width.');
            foreach ($row->values as $value) {
                $pending[] = [$value, 0, 0];
            }
        }
        (new ExpressionScope())->check([], $pending);
        $this->rows = $checked;
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Writes every row in the recorded parameter order.
     */
    public function toString(): string
    {
        $rows = [];
        foreach ($this->rows as $row) {
            $rows[] = $row->toString();
        }

        return 'VALUES ' . implode(', ', $rows);
    }
}
