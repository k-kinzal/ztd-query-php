<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Inspection;

/**
 * What an EXPLAIN prefix asks SQLite to report instead of running the statement.
 *
 * Source: https://sqlite.org/lang_explain.html.
 *
 * @visibility SqlSemantics
 */
enum ExplainMode
{
    case Program;
    case QueryPlan;
}
