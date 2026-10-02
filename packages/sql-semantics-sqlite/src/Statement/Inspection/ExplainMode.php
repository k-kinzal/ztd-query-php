<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Inspection;

/**
 * What an EXPLAIN prefix asks SQLite to report instead of running the statement.
 *
 * Source: https://sqlite.org/lang_explain.html.
 *
 * @visibility public
 * @example Telling the two inspection requests apart
 *     $explain = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('EXPLAIN QUERY PLAN SELECT 1');
 *     $explain->statement->mode // => \SqlSemantics\Platform\Sqlite\Statement\Inspection\ExplainMode::QueryPlan
 */
enum ExplainMode
{
    case Program;
    case QueryPlan;
}
