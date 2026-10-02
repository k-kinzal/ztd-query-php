<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Two column counts that SQLite requires to agree and that differ.
 *
 * @visibility public
 * @example Reading the counts of an INSERT that supplies too few values
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER, b TEXT)');
 *     $problem = $semantics->analyze('INSERT INTO t VALUES (1)', [$table])->facts->diagnostics[0];
 *     [$problem->expected, $problem->actual] // => [2, 1]
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
        };
    }
}
