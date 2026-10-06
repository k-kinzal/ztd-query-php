<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Inspection;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ExplainRows;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to report how SQLite would run a statement instead of running it.
 *
 * Rule: SQLITE-EXPLAIN-001. EXPLAIN reports the virtual machine program of
 * the statement, EXPLAIN QUERY PLAN the strategy of its queries; they are two
 * different requests. The wrapped statement is inspected, not executed: every
 * part of it is derived as usual, so its name resolutions and diagnostics are
 * facts of the operation, but the rows it would return and the declaration a
 * wrapped CREATE would provide are discarded. The operation returns the rows
 * of the report (SQLITE-EXPLAIN-ROWS-001) and provides no declaration.
 * Source: https://sqlite.org/lang_explain.html, https://sqlite.org/eqp.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the columns of a query plan report
 *     $explain = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('EXPLAIN QUERY PLAN SELECT 1');
 *     [count($explain->fields()), $explain->field(3)->name?->value, $explain->toString()] // => [4, 'detail', 'EXPLAIN QUERY PLAN SELECT 1']
 * @example Inspecting a definition without declaring it
 *     $explain = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('EXPLAIN CREATE TABLE t (a)');
 *     [$explain->declarations(), count($explain->fields())] // => [[], 8]
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
     * Inspects the wrapped statement and records the rows of the report as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->inspected($this->statement);
        $derivation->output((new ExplainRows())->fact($this->mode, $derivation->context->columnNames));
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
