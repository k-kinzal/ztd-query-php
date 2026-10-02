<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Inspection;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ExplainRows;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to report how SQLite would run a statement instead of running it.
 *
 * Rule: SQLITE-EXPLAIN-001. EXPLAIN reports the virtual machine program of
 * the statement, EXPLAIN QUERY PLAN the strategy of its queries; they are two
 * different requests. The wrapped statement is not executed. Its parts are
 * derived as usual, so its name resolutions and diagnostics are facts of the
 * operation. The operation returns the rows of the report (ExplainRows), not
 * the rows of the wrapped statement.
 *
 * Remaining assumption: the derivation recorder publishes the output of a
 * nested statement that is not a plain query (a write with RETURNING) and the
 * declaration of a nested CREATE as if they were those of the root; until the
 * recorder can derive a nested statement without them, such an operation
 * keeps the nested output and reports the nested declaration.
 * Source: https://sqlite.org/lang_explain.html, https://sqlite.org/eqp.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the columns of a query plan report
 *     $explain = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('EXPLAIN QUERY PLAN SELECT 1');
 *     [count($explain->fields()), $explain->field(3)->name?->value, $explain->toString()] // => [4, 'detail', 'EXPLAIN QUERY PLAN SELECT 1']
 */
final class Explain implements Statement
{
    use Snapshot;

    /**
     * @param ExplainMode $mode Which report is requested
     * @param Statement $statement The statement to report on
     */
    public function __construct(public readonly ExplainMode $mode, public readonly Statement $statement)
    {
    }

    /**
     * Derives the wrapped statement and records the rows of the report as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->statement instanceof Query) {
            $derivation->query($this->statement, $derivation->environment());
        } else {
            $derivation->statement($this->statement);
        }
        if ($derivation->facts()->output === null) {
            $derivation->output((new ExplainRows())->fact($this->mode, $derivation->context->columnNames));
        }
    }

    /**
     * Writes the prefix and the wrapped statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXPLAIN');
        if ($this->mode === ExplainMode::QueryPlan) {
            $out->keyword('QUERY', 'PLAN');
        }
        $out->node($this->statement);
    }
}
