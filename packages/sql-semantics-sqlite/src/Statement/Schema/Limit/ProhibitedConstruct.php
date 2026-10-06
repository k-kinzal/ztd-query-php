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
 * @visibility public
 * @example Reading the construct of a prohibited expression
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a, b AS (random()))');
 *     $create->facts->diagnostics[0]->construct // => \SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\ProhibitedConstruct::NonDeterministicFunction
 */
enum ProhibitedConstruct: string
{
    case Parameter = 'parameters';
    case Subquery = 'subqueries';
    case DotOperator = 'the "." operator';
    case NonDeterministicFunction = 'non-deterministic functions';
}
