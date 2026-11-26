<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Two column counts that SQLite requires to agree and that differ.
 *
 * @visibility SqlSemantics
 */
final class ArityMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param ArityRule $rule The place where the counts must agree
     * @param int $expected The count the place requires
     * @param int $actual The count the statement supplies
     */
    public function __construct(public readonly ArityRule $rule, public readonly int $expected, public readonly int $actual)
    {
    }

    /**
     * Describes the disagreement.
     */
    public function message(): string
    {
        return match ($this->rule) {
            ArityRule::CompoundArms => 'SELECTs to the left and right of a compound operator do not have the same number of result columns: ' . $this->expected . ' and ' . $this->actual . '.',
            ArityRule::ValueRows => 'All VALUES must have the same number of terms: ' . $this->expected . ' and ' . $this->actual . '.',
            ArityRule::InsertedValues => $this->actual . ' values for ' . $this->expected . ' columns.',
            ArityRule::CommonTableColumns => 'A common table has ' . $this->actual . ' values for ' . $this->expected . ' columns.',
            ArityRule::RowComparison => 'Row value misused: ' . $this->expected . ' columns against ' . $this->actual . '.',
            ArityRule::RowAssignment => $this->expected . ' columns assigned ' . $this->actual . ' values.',
            ArityRule::ScalarSubquery => 'Sub-select returns ' . $this->actual . ' columns - expected ' . $this->expected . '.',
            ArityRule::InListElement => 'IN(...) element has ' . $this->actual . ' term' . ($this->actual === 1 ? '' : 's') . ' - expected ' . $this->expected . '.',
        };
    }
}
