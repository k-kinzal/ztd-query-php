<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Limit;

/**
 * The constructs SQLite rejects inside the expressions of a definition.
 *
 * The value is the phrase SQLite uses for the construct in its error message.
 * Source: https://sqlite.org/lang_createtable.html#check_constraints,
 * https://sqlite.org/partialindex.html, https://sqlite.org/expridx.html,
 * https://sqlite.org/gencol.html.
 *
 * @visibility SqlSemantics
 */
enum ProhibitedConstruct: string
{
    case Parameter = 'parameters';
    case Subquery = 'subqueries';
    case DotOperator = 'the "." operator';
    case NonDeterministicFunction = 'non-deterministic functions';
}
